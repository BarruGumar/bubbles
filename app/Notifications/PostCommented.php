<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PostCommented extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private User $commenter,
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
            'type' => 'post_commented',
            'message' => "{$this->commenter->name} comentou no teu post.",
            'sender_id' => $this->commenter->id,
            'sender_name' => $this->commenter->name,
            'sender_username' => $this->commenter->username,
            'sender_avatar' => $this->commenter->avatar,
            'sender_color' => $this->commenter->avatar_color ?? '#009ac7',
            'post_id' => $this->postId,
            'post_type' => $this->postType,
            'comment_excerpt' => mb_strimwidth($this->excerpt, 0, 80, '…'),
            'url' => $this->postType === 'community_post' && $this->bubbleId
                ? "/c/{$this->bubbleId}"
                : "/u/{$notifiable->username}",
        ];
    }
}
