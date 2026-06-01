<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int   $recipientId,
        public array $conversationData,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel('user.' . $this->recipientId);
    }

    public function broadcastAs(): string
    {
        return 'ConversationCreated';
    }

    public function broadcastWith(): array
    {
        return ['conversation' => $this->conversationData];
    }
}
