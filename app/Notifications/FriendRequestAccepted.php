<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FriendRequestAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private User $accepter) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'friend_request_accepted',
            'message' => "{$this->accepter->name} aceitou o teu pedido de amizade.",
            'sender_id' => $this->accepter->id,
            'sender_name' => $this->accepter->name,
            'sender_username' => $this->accepter->username,
            'sender_avatar' => $this->accepter->avatar,
            'sender_color' => $this->accepter->avatar_color ?? '#009ac7',
            'url' => "/u/{$this->accepter->username}",
        ];
    }
}
