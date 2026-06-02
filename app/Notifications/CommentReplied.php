<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class CommentReplied extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private User $replier,
        private int $postId,
        private string $excerpt,
        private string $postType = 'post',
        private ?int $bubbleId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                => 'comment_replied',
            'message'             => "{$this->replier->name} respondeu ao teu comentário.",
            'commenter_id'        => $this->replier->id,
            'commenter_name'      => $this->replier->name,
            'commenter_username'  => $this->replier->username,
            'commenter_avatar'    => $this->replier->avatar,
            'commenter_color'     => $this->replier->avatar_color ?? '#009ac7',
            'post_id'             => $this->postId,
            'post_type'           => $this->postType,
            'comment_excerpt'     => mb_strimwidth($this->excerpt, 0, 80, '…'),
            'url'                 => $this->postType === 'community_post' && $this->bubbleId
                ? "/c/{$this->bubbleId}"
                : "/u/{$notifiable->username}",
        ];
    }
}
