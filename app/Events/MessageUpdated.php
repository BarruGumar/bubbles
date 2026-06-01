<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int   $conversationId,
        public int   $messageId,
        public string $content,
        public bool  $isEdited,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('conversation.' . $this->conversationId);
    }

    public function broadcastAs(): string
    {
        return 'MessageUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->messageId,
            'content'    => $this->content,
            'is_edited'  => $this->isEdited,
        ];
    }
}
