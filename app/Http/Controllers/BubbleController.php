<?php

namespace App\Http\Controllers;

use App\Events\BubbleMoved;
use App\Models\Bubble;
use App\Models\Friend;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BubbleController extends Controller
{
        public function showPage(Bubble $bubble): Response
    {
        return Inertia::render('Community/Show', [
            'bubble' => $bubble,
        ]);
    }

    public function index(): JsonResponse
    {
        $memberIds = auth()->check()
            ? auth()->user()->communities()->pluck('bubbles.id')->toArray()
            : [];

        $bubbles = Bubble::withCount('memberships')->latest('id')->get()->map(fn ($b) => [
            'id'              => $b->id,
            'label'           => $b->label,
            'color'           => $b->color,
            'x'               => $b->x,
            'y'               => $b->y,
            'size'            => $b->size,
            'members'         => $b->memberships_count,
            'is_member'       => in_array($b->id, $memberIds),
            'community_image' => $b->community_image,
        ]);

        return response()->json($bubbles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'                => ['nullable', 'exists:users,id'],
            'label'                  => ['required', 'string', 'max:120'],
            'color'                  => ['nullable', 'string', 'max:40'],
            'x'                      => ['required', 'numeric'],
            'y'                      => ['required', 'numeric'],
            'size'                   => ['nullable', 'integer', 'min:40', 'max:220'],
            'community_title'        => ['nullable', 'string', 'max:120'],
            'community_description'  => ['nullable', 'string', 'max:1000'],
            'community_cover_color'  => ['nullable', 'string', 'max:40'],
            'community_tagline'      => ['nullable', 'string', 'max:160'],
            'community_guidelines'   => ['nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        $bubble = Bubble::create($data);

        if (auth()->check()) {
            $bubble->memberships()->attach(auth()->id());
        }

        return response()->json(['id' => $bubble->id], 201);
    }

    public function update(Request $request, Bubble $bubble): JsonResponse
    {
        $data = $request->validate([
            'label' => ['sometimes', 'required', 'string', 'max:120'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'x' => ['sometimes', 'required', 'numeric'],
            'y' => ['sometimes', 'required', 'numeric'],
            'size' => ['sometimes', 'nullable', 'integer', 'min:40', 'max:220'],
            'members' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'community_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'community_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'community_cover_color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'community_tagline' => ['sometimes', 'nullable', 'string', 'max:160'],
            'community_guidelines' => ['sometimes', 'nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        $bubble->update($data);

        if (array_key_exists('x', $data) || array_key_exists('y', $data)) {
            event(new BubbleMoved($bubble->fresh()));
        }

        return response()->json($bubble->fresh());
    }

    public function destroy(Bubble $bubble): JsonResponse
    {
        $bubble->delete();

        return response()->json(status: 204);
    }

    public function friendConnections(): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json([]);
        }

        $user = auth()->user();

        $friendIds = Friend::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)->orWhere('friend_id', $user->id);
            })
            ->get()
            ->map(fn ($f) => $f->user_id === $user->id ? $f->friend_id : $f->user_id)
            ->unique()
            ->values();

        if ($friendIds->isEmpty()) {
            return response()->json([]);
        }

        $friendCommunities = DB::table('community_user')
            ->whereIn('user_id', $friendIds)
            ->get(['user_id', 'community_id'])
            ->groupBy('user_id');

        $pairs = collect();

        foreach ($friendCommunities as $friendId => $memberships) {
            $communityIds = $memberships->pluck('community_id')->toArray();
            if (count($communityIds) < 2) {
                continue;
            }

            for ($i = 0; $i < count($communityIds); $i++) {
                for ($j = $i + 1; $j < count($communityIds); $j++) {
                    $from  = min($communityIds[$i], $communityIds[$j]);
                    $to    = max($communityIds[$i], $communityIds[$j]);
                    $key   = "{$from}-{$to}";
                    $entry = $pairs->get($key, ['from' => $from, 'to' => $to, 'friendIds' => []]);

                    if (! in_array((int) $friendId, $entry['friendIds'])) {
                        $entry['friendIds'][] = (int) $friendId;
                    }

                    $pairs->put($key, $entry);
                }
            }
        }

        if ($pairs->isEmpty()) {
            return response()->json([]);
        }

        $allFriendIds = $pairs->flatMap(fn ($p) => $p['friendIds'])->unique()->values();
        $nameMap      = User::whereIn('id', $allFriendIds)->get(['id', 'name'])->keyBy('id');

        $result = $pairs->values()->map(fn ($pair) => [
            'from'        => $pair['from'],
            'to'          => $pair['to'],
            'friendNames' => collect($pair['friendIds'])
                ->map(fn ($id) => $nameMap->get($id)?->name)
                ->filter()
                ->values()
                ->toArray(),
        ]);

        return response()->json($result);
    }
}
