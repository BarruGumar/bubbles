<?php

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{id}', function ($user, $id) {
    return DB::table('conversation_user')
        ->where('conversation_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});

Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('online', function ($user) {
    if ($user->isBanned()) {
        return false;
    }
    return ['id' => $user->id, 'name' => $user->name];
});
