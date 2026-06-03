<?php

namespace App\Http\Controllers;

use App\Events\BubbleMoved;
use App\Models\Bubble;
use App\Models\Friend;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
        $authId = auth()->id();
        $memberIds = $authId
            ? Cache::remember("user:{$authId}:community_ids", now()->addMinutes(5), fn () => auth()->user()->communities()->pluck('bubbles.id')->toArray())
            : [];

        // Cache global bubble data for 1 minute — metadata changes infrequently.
        // is_member is user-specific and merged separately from the cached memberIds.
        $rawBubbles = Cache::remember('bubbles:all', now()->addMinutes(1), function () {
            return Bubble::withCount('memberships')->latest('id')->get()->map(fn ($b) => [
                'id'              => $b->id,
                'label'           => $b->label,
                'color'           => $b->color,
                'x'               => $b->x,
                'y'               => $b->y,
                'size'            => $b->size,
                'members'         => $b->memberships_count,
                'community_image' => $b->community_image,
            ])->all();
        });

        $memberSet = array_flip($memberIds);
        $bubbles = array_map(fn ($b) => $b + ['is_member' => isset($memberSet[$b['id']])], $rawBubbles);

        return response()->json($bubbles);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'color' => ['nullable', 'string', 'max:40'],
            'x' => ['required', 'numeric'],
            'y' => ['required', 'numeric'],
            'size' => ['nullable', 'integer', 'min:40', 'max:220'],
            'community_title' => ['nullable', 'string', 'max:120'],
            'community_description' => ['nullable', 'string', 'max:1000'],
            'community_cover_color' => ['nullable', 'string', 'max:40'],
            'community_tagline' => ['nullable', 'string', 'max:160'],
            'community_guidelines' => ['nullable', 'array', 'max:5'],
            'community_guidelines.*' => ['string', 'max:180'],
        ]);

        $data['user_id'] = auth()->id();

        $bubble = Bubble::create($data);
        $bubble->memberships()->attach($data['user_id']);

        Cache::forget("user:{$data['user_id']}:community_ids");
        Cache::forget('bubbles:all');

        AuditLogger::log('community.created', 'community', $bubble, [
            'label' => $bubble->label,
        ], $bubble->id);

        return response()->json(['id' => $bubble->id], 201);
    }

    public function update(Request $request, Bubble $bubble): JsonResponse
    {
        Gate::authorize('update', $bubble);

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

        // Invalidate global bubble cache on any metadata change
        if (array_intersect(array_keys($data), ['label', 'color', 'size', 'community_image'])) {
            Cache::forget('bubbles:all');
        }

        return response()->json($bubble->fresh());
    }

    public function destroy(Bubble $bubble): JsonResponse
    {
        Gate::authorize('delete', $bubble);

        $authId = auth()->id();

        AuditLogger::log('community.deleted', 'community', null, [
            'community_id' => $bubble->id,
            'label' => $bubble->label,
        ], $bubble->id);

        Cache::forget("user:{$authId}:community_ids");
        Cache::forget('bubbles:all');

        $bubble->delete();

        return response()->json(status: 204);
    }

    public function friendConnections(): JsonResponse
    {
        if (! auth()->check()) {
            return response()->json([]);
        }

        $user = auth()->user();

        $friendIds = collect(Cache::remember("user:{$user->id}:friend_ids", now()->addMinutes(5), function () use ($user) {
            return Friend::where('status', 'accepted')
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('friend_id', $user->id))
                ->get()
                ->map(fn ($f) => $f->user_id === $user->id ? $f->friend_id : $f->user_id)
                ->unique()
                ->values()
                ->toArray();
        }));

        if ($friendIds->isEmpty()) {
            return response()->json([]);
        }

        $friendCommunities = DB::table('community_user')
            ->whereIn('user_id', $friendIds)
            ->get(['user_id', 'community_id'])
            ->groupBy('user_id');

        // Each friend contributes exactly ONE pair (their two lowest community IDs).
        // Pairs from different friends merge if they share the same two communities.
        // Hard cap: 8 pairs total to keep the canvas readable.
        $pairs = collect();

        foreach ($friendCommunities as $friendId => $memberships) {
            $communityIds = $memberships->pluck('community_id')->sort()->values()->toArray();
            if (count($communityIds) < 2) {
                continue;
            }

            $from = $communityIds[0];
            $to = $communityIds[1];
            $key = "{$from}-{$to}";

            if ($pairs->has($key)) {
                $entry = $pairs->get($key);
                if (! in_array((int) $friendId, $entry['friendIds'])) {
                    $entry['friendIds'][] = (int) $friendId;
                    $pairs->put($key, $entry);
                }
            } else {
                if ($pairs->count() >= 8) {
                    continue;
                }
                $pairs->put($key, ['from' => $from, 'to' => $to, 'friendIds' => [(int) $friendId]]);
            }
        }

        if ($pairs->isEmpty()) {
            return response()->json([]);
        }

        $allFriendIds = $pairs->flatMap(fn ($p) => $p['friendIds'])->unique()->values()->toArray();

        // Plain PHP array keyed by int ID — avoids Collection key-type issues.
        $userMap = [];
        foreach (User::whereIn('id', $allFriendIds)->get(['id', 'name', 'avatar', 'avatar_color']) as $u) {
            $userMap[(int) $u->id] = $u;
        }

        $result = $pairs->values()->map(function ($pair) use ($userMap) {
            $friends = [];
            foreach ($pair['friendIds'] as $id) {
                $u = $userMap[(int) $id] ?? null;
                if ($u) {
                    $friends[] = [
                        'name' => $u->name,
                        'avatar' => $u->avatar,
                        'avatar_color' => $u->avatar_color ?? '#9b6bdf',
                    ];
                }
            }

            return [
                'from' => $pair['from'],
                'to' => $pair['to'],
                'friends' => $friends,
            ];
        });

        return response()->json($result);
    }
}
