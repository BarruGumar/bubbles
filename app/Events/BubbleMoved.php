<?php

namespace App\Events;

use App\Models\Bubble;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BubbleMoved
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Bubble $bubble)
    {
    }
}