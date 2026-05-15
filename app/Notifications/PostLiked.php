<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostLiked extends Notification
{
    use Queueable;

    public function __construct(
        private User $liker,
        private int $postId,
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
            'type' => 'post_liked',
            'message' => "{$this->liker->name} gostou do teu post.",
            'liker_id' => $this->liker->id,
            'liker_name' => $this->liker->name,
            'liker_username' => $this->liker->username,
            'liker_avatar' => $this->liker->avatar,
            'liker_color' => $this->liker->avatar_color ?? '#009ac7',
            'post_id' => $this->postId,
            'post_type' => $this->postType,
            'url' => $this->postType === 'community_post' && $this->bubbleId
                ? "/c/{$this->bubbleId}"
                : "/u/{$notifiable->username}",
        ];
    }
}
