<?php

namespace App\Services;

use App\Events\BadgeCountUpdated;
use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Support\Str;

class NotificationService
{
    public static function send(User $recipient, $notification): void
    {
        $recipient->notify($notification);
        $data = $notification->toArray($recipient);

        broadcast(new BadgeCountUpdated($recipient->id, 'notifications', 1));
        broadcast(new NotificationCreated($recipient->id, [
            'id'         => (string) Str::uuid(),
            'type'       => $data['type'],
            'message'    => $data['message'],
            'data'       => $data,
            'read'       => false,
            'created_at' => 'agora',
            'url'        => $data['url'] ?? null,
        ]));
    }
}
