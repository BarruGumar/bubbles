<?php

namespace App\Support;

trait FormatsCommentData
{
    protected function mapComment($c, int $authId, bool $includeReplies = true): array
    {
        return [
            'id'            => $c->id,
            'content'       => $c->content,
            'created_at'    => $c->created_at->diffForHumans(),
            'is_own'        => $c->user_id === $authId,
            'likes_count'   => $c->likes->count(),
            'user_reaction' => $c->likes->where('user_id', $authId)->first()?->type ?? null,
            'like_route'    => route('comments.like', $c->id),
            'reply_route'   => route('comments.replies.store', $c->id),
            'author'        => [
                'name'         => $c->user->name,
                'username'     => $c->user->username,
                'avatar'       => $c->user->avatar,
                'avatar_color' => $c->user->avatar_color ?? '#009ac7',
                'role'         => $c->user->role,
            ],
            'replies'       => $includeReplies
                ? $c->replies->map(fn ($r) => $this->mapComment($r, $authId, false))->values()->all()
                : [],
        ];
    }
}
