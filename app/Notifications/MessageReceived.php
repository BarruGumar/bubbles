<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        private User $sender,
        private Conversation $conversation,
        private string $excerpt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'message_received',
            'message' => "{$this->sender->name} enviou-te uma mensagem.",
            'sender_id' => $this->sender->id,
            'sender_name' => $this->sender->name,
            'sender_username' => $this->sender->username,
            'sender_avatar' => $this->sender->avatar,
            'sender_color' => $this->sender->avatar_color ?? '#009ac7',
            'conversation_id' => $this->conversation->id,
            'excerpt' => mb_strimwidth($this->excerpt, 0, 60, '…'),
            'url' => "/conversations/{$this->conversation->id}",
        ];
    }
}
