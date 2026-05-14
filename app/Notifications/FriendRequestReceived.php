<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class FriendRequestReceived extends Notification
{
    use Queueable;

    public function __construct(private User $sender) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'       => 'friend_request_received',
            'message'    => "{$this->sender->name} enviou-te um pedido de amizade.",
            'sender_id'  => $this->sender->id,
            'sender_name'=> $this->sender->name,
            'sender_username' => $this->sender->username,
            'sender_avatar'   => $this->sender->avatar,
            'sender_color'    => $this->sender->avatar_color ?? '#009ac7',
            'url'        => "/u/{$this->sender->username}",
        ];
    }
}
