<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PostCommented extends Notification
{
    use Queueable;

    public function __construct(
        private User   $commenter,
        private int    $postId,
        private string $excerpt,
        private string $postType = 'post',
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'                 => 'post_commented',
            'message'              => "{$this->commenter->name} comentou no teu post.",
            'commenter_id'         => $this->commenter->id,
            'commenter_name'       => $this->commenter->name,
            'commenter_username'   => $this->commenter->username,
            'commenter_avatar'     => $this->commenter->avatar,
            'commenter_color'      => $this->commenter->avatar_color ?? '#009ac7',
            'post_id'              => $this->postId,
            'post_type'            => $this->postType,
            'comment_excerpt'      => mb_strimwidth($this->excerpt, 0, 80, '…'),
            'url'                  => "/u/{$notifiable->username}",
        ];
    }
}
