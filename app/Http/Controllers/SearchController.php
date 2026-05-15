<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const LIMIT = 15;

    public function index(Request $request): Response
    {
        $q = trim($request->query('q', ''));

        return Inertia::render('Search/Index', [
            'query' => $q,
            'results' => $q !== '' ? $this->search($q) : null,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if ($q === '') {
            return response()->json(['users' => [], 'communities' => [], 'posts' => []]);
        }

        return response()->json($this->search($q));
    }

    private function search(string $q): array
    {
        $like = '%'.$q.'%';

        $users = User::where('name', 'like', $like)
            ->orWhere('username', 'like', $like)
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'avatar' => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
                'bio' => $u->bio,
            ])->values();

        $communities = Bubble::where('label', 'like', $like)
            ->orWhere('community_title', 'like', $like)
            ->orWhere('community_description', 'like', $like)
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'label' => $b->label,
                'title' => $b->community_title ?: $b->label,
                'description' => $b->community_description,
                'color' => $b->color ?? '#009ac7',
                'image' => $b->community_image,
                'members' => $b->members ?? 0,
            ])->values();

        $posts = Post::where('content', 'like', $like)
            ->with('user')
            ->latest()
            ->limit(self::LIMIT)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'content' => $p->content,
                'image' => $p->image,
                'created_at' => $p->created_at->diffForHumans(),
                'author' => [
                    'id' => $p->user->id,
                    'name' => $p->user->name,
                    'username' => $p->user->username,
                    'avatar' => $p->user->avatar,
                    'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                ],
            ])->values();

        return [
            'users' => $users,
            'communities' => $communities,
            'posts' => $posts,
        ];
    }
}
