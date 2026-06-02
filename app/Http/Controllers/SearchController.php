<?php

namespace App\Http\Controllers;

use App\Models\Bubble;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const LIMIT_API  = 5;
    private const LIMIT_PAGE = 12;

    public function index(Request $request): Response
    {
        $q = trim($request->query('q', ''));

        return Inertia::render('Search/Index', [
            'query'   => $q,
            'results' => mb_strlen($q) >= 2 ? $this->search($q, self::LIMIT_PAGE) : null,
        ]);
    }

    public function api(Request $request): JsonResponse
    {
        $q = trim($request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['users' => [], 'communities' => [], 'posts' => []]);
        }

        return response()->json($this->search($q, self::LIMIT_API));
    }

    private function search(string $q, int $limit): array
    {
        // Escape LIKE wildcards so user input cannot alter the search pattern
        $safe = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q);
        $like = '%' . $safe . '%';

        // FULLTEXT index lookup (MySQL only, min 3 chars due to innodb_ft_min_token_size)
        $useFulltext = mb_strlen($q) >= 3 && DB::connection()->getDriverName() === 'mysql';

        $blockedIds = [];
        if ($authId = auth()->id()) {
            $blockedIds = UserBlock::mutualIds($authId);
        }

        $users = User::when(
                $useFulltext,
                fn ($b) => $b->whereFullText(['name', 'username'], $q . '*', ['mode' => 'boolean']),
                fn ($b) => $b->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('username', 'like', $like))
            )
            ->when($blockedIds, fn ($q) => $q->whereNotIn('id', $blockedIds))
            ->limit($limit + 5)
            ->get(['id', 'name', 'username', 'avatar', 'avatar_color', 'bio'])
            ->map(fn ($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'username'     => $u->username,
                'avatar'       => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
                'bio'          => $u->bio,
                '_score'       => $this->score($q, [$u->name, $u->username]),
            ])
            ->sortByDesc('_score')
            ->take($limit)
            ->map(fn ($u) => collect($u)->except('_score')->all())
            ->values();

        $communities = Bubble::when(
                $useFulltext,
                fn ($b) => $b->whereFullText(['label', 'community_title', 'community_description'], $q . '*', ['mode' => 'boolean']),
                fn ($b) => $b->where(fn ($w) => $w->where('label', 'like', $like)
                    ->orWhere('community_title', 'like', $like)
                    ->orWhere('community_description', 'like', $like))
            )
            ->select(['id', 'label', 'community_title', 'community_description', 'color', 'community_image'])
            ->withCount('memberships')
            ->limit($limit + 5)
            ->get()
            ->map(fn ($b) => [
                'id'          => $b->id,
                'label'       => $b->label,
                'title'       => $b->community_title ?: $b->label,
                'description' => $b->community_description,
                'color'       => $b->color ?? '#009ac7',
                'image'       => $b->community_image,
                'members'     => $b->memberships_count,
                '_score'      => $this->score($q, [$b->label, $b->community_title]),
            ])
            ->sortByDesc('_score')
            ->take($limit)
            ->map(fn ($b) => collect($b)->except('_score')->all())
            ->values();

        $posts = Post::when(
                $useFulltext,
                fn ($b) => $b->whereFullText(['content'], $q . '*', ['mode' => 'boolean']),
                fn ($b) => $b->where('content', 'like', $like)
            )
            ->with(['user' => fn ($q) => $q->select(['id', 'name', 'username', 'avatar', 'avatar_color', 'role'])])
            ->latest()
            ->limit($limit)
            ->get(['id', 'user_id', 'content', 'image', 'created_at'])
            ->map(fn ($p) => [
                'id'         => $p->id,
                'content'    => $p->content,
                'image'      => $p->image,
                'created_at' => $p->created_at->diffForHumans(),
                'author'     => [
                    'id'           => $p->user->id,
                    'name'         => $p->user->name,
                    'username'     => $p->user->username,
                    'avatar'       => $p->user->avatar,
                    'avatar_color' => $p->user->avatar_color ?? '#009ac7',
                    'role'         => $p->user->role,
                ],
            ])->values();

        return compact('users', 'communities', 'posts');
    }

    // Score: exact match = 3, prefix = 2, contains = 1, no match = 0
    private function score(string $q, array $fields): int
    {
        $q    = mb_strtolower($q);
        $best = 0;
        foreach ($fields as $field) {
            if ($field === null) continue;
            $f = mb_strtolower($field);
            if ($f === $q)               { $best = max($best, 3); continue; }
            if (str_starts_with($f, $q)) { $best = max($best, 2); continue; }
            if (str_contains($f, $q))      $best = max($best, 1);
        }
        return $best;
    }
}
