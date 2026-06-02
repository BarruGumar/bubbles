<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateGroupRequest;
use App\Http\Requests\GroupMemberRequest;
use App\Http\Requests\UpdateGroupRequest;
use App\Models\Conversation;
use App\Models\Friend;
use App\Models\User;
use App\Support\ImageUploadPresets;
use App\Support\StoresImages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    use StoresImages;

    public function store(CreateGroupRequest $request): RedirectResponse
    {
        $user = $request->user();
        $participantIds = collect($request->input('participant_ids'))
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === $user->id)
            ->unique()
            ->values();

        // Validate that invited users exist (extra safety beyond Form Request)
        $validIds = User::whereIn('id', $participantIds)->pluck('id');
        abort_if($validIds->count() < 2, 422, 'Seleciona pelo menos 2 participantes válidos.');

        $avatarUrl = null;
        if ($request->hasFile('image')) {
            ['url' => $avatarUrl] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/groups',
                ImageUploadPresets::post()
            );
        }

        $conversation = DB::transaction(function () use ($user, $request, $avatarUrl, $validIds) {
            $conv = Conversation::create([
                'type'     => 'group',
                'name'     => $request->input('name'),
                'description' => $request->input('description'),
                'avatar'   => $avatarUrl,
                'owner_id' => $user->id,
            ]);

            // Attach owner
            $conv->participants()->attach($user->id, [
                'role'      => 'owner',
                'joined_at' => now(),
            ]);

            // Attach members
            $members = [];
            foreach ($validIds as $id) {
                $members[$id] = ['role' => 'member', 'joined_at' => now()];
            }
            $conv->participants()->attach($members);

            return $conv;
        });

        return redirect()->route('conversations.show', $conversation->id);
    }

    public function update(UpdateGroupRequest $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);
        $this->authorizeRole($conversation, ['owner', 'admin']);

        $data = [];

        if ($request->has('name')) {
            $data['name'] = $request->input('name');
        }
        if ($request->has('description')) {
            $data['description'] = $request->input('description');
        }

        if ($request->hasFile('image')) {
            ['url' => $avatarUrl] = $this->storeImageWithMeta(
                $request->file('image'),
                'bubbles/groups',
                ImageUploadPresets::post()
            );
            $data['avatar'] = $avatarUrl;
        }

        $conversation->update($data);

        return response()->json([
            'name'   => $conversation->name,
            'avatar' => $conversation->avatar,
        ]);
    }

    public function addMember(GroupMemberRequest $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);
        $this->authorizeRole($conversation, ['owner', 'admin']);

        $targetId = (int) $request->input('user_id');
        abort_if($targetId === auth()->id(), 422, 'Já és membro deste grupo.');

        $alreadyIn = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $targetId)
            ->exists();

        abort_if($alreadyIn, 422, 'Este utilizador já é membro do grupo.');

        $target = User::findOrFail($targetId);

        $conversation->participants()->attach($targetId, [
            'role'      => 'member',
            'joined_at' => now(),
        ]);

        return response()->json([
            'user' => [
                'id'           => $target->id,
                'name'         => $target->name,
                'username'     => $target->username,
                'avatar'       => $target->avatar,
                'avatar_color' => $target->avatar_color ?? '#009ac7',
                'role'         => 'member',
            ],
        ]);
    }

    public function removeMember(Conversation $conversation, User $user): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);

        $actorRole  = $this->getRole($conversation, auth()->id());
        $targetRole = $this->getRole($conversation, $user->id);

        abort_if($actorRole === null, 403);

        // Cannot remove the owner
        abort_if($targetRole === 'owner', 403, 'Não é possível remover o owner do grupo.');

        // Admin can only remove members, not other admins
        if ($actorRole === 'admin') {
            abort_unless($targetRole === 'member', 403, 'Admins só podem remover membros comuns.');
        }

        // Member cannot remove anyone
        abort_if($actorRole === 'member', 403, 'Não tens permissão para remover membros.');

        // Cannot leave the group empty
        $count = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->count();
        abort_if($count <= 1, 422, 'Não é possível remover o último membro do grupo.');

        $conversation->participants()->detach($user->id);

        return response()->json(['ok' => true]);
    }

    public function leave(Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);

        $userId = auth()->id();
        $role   = $this->getRole($conversation, $userId);
        abort_if($role === null, 403, 'Não és membro deste grupo.');

        $count = DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->count();

        if ($role === 'owner') {
            // Owner can only leave if they are the last member or have transferred ownership
            abort_unless($count === 1, 422, 'Transfere a liderança do grupo antes de sair.');
        }

        $conversation->participants()->detach($userId);

        // If last member left, clean up the conversation
        if ($count === 1) {
            $conversation->delete();
            return response()->json(['ok' => true, 'deleted' => true]);
        }

        return response()->json(['ok' => true, 'deleted' => false]);
    }

    public function promoteRole(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);
        $this->authorizeRole($conversation, ['owner']);

        $targetId   = (int) $request->validate(['user_id' => 'required|integer|exists:users,id'])['user_id'];
        $targetRole = $this->getRole($conversation, $targetId);

        abort_if($targetRole === null, 404, 'Utilizador não é membro do grupo.');
        abort_if($targetRole === 'owner', 422, 'Não podes promover o owner.');
        abort_if($targetRole === 'admin', 422, 'Este membro já é admin.');

        $conversation->participants()->updateExistingPivot($targetId, ['role' => 'admin']);

        return response()->json(['role' => 'admin']);
    }

    public function demoteRole(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);
        $this->authorizeRole($conversation, ['owner']);

        $targetId   = (int) $request->validate(['user_id' => 'required|integer|exists:users,id'])['user_id'];
        $targetRole = $this->getRole($conversation, $targetId);

        abort_if($targetRole === null, 404, 'Utilizador não é membro do grupo.');
        abort_if($targetRole === 'owner', 422, 'Não podes rebaixar o owner.');
        abort_if($targetRole === 'member', 422, 'Este membro já é member.');

        $conversation->participants()->updateExistingPivot($targetId, ['role' => 'member']);

        return response()->json(['role' => 'member']);
    }

    public function transferOwner(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 403);
        $this->authorizeRole($conversation, ['owner']);

        $targetId   = (int) $request->validate(['user_id' => 'required|integer|exists:users,id'])['user_id'];
        $targetRole = $this->getRole($conversation, $targetId);

        abort_if($targetRole === null, 404, 'Utilizador não é membro do grupo.');
        abort_if($targetId === auth()->id(), 422, 'Já és o owner.');

        DB::transaction(function () use ($conversation, $targetId) {
            $conversation->participants()->updateExistingPivot(auth()->id(), ['role' => 'admin']);
            $conversation->participants()->updateExistingPivot($targetId, ['role' => 'owner']);
            $conversation->update(['owner_id' => $targetId]);
        });

        return response()->json(['ok' => true]);
    }

    public function friends(): JsonResponse
    {
        return response()->json(['friends' => Friend::friendsOf(auth()->id())]);
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function getRole(Conversation $conversation, int $userId): ?string
    {
        return DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $userId)
            ->value('role');
    }

    private function authorizeRole(Conversation $conversation, array $allowedRoles): void
    {
        $role = $this->getRole($conversation, auth()->id());
        abort_unless(in_array($role, $allowedRoles, true), 403, 'Não tens permissão para esta ação.');
    }
}
