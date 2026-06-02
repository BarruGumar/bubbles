<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Friend extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'friend_id', 'status'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public static function clearFriendCaches(int ...$userIds): void
    {
        foreach ($userIds as $id) {
            Cache::forget("user:{$id}:friend_ids");
        }
    }

    public static function friendsOf(int $userId): \Illuminate\Support\Collection
    {
        return self::where('status', 'accepted')
            ->where(fn ($q) => $q->where('user_id', $userId)->orWhere('friend_id', $userId))
            ->with(['user', 'friend'])
            ->get()
            ->map(fn ($f) => $f->user_id === $userId ? $f->friend : $f->user)
            ->map(fn ($u) => [
                'id'           => $u->id,
                'name'         => $u->name,
                'username'     => $u->username,
                'avatar'       => $u->avatar,
                'avatar_color' => $u->avatar_color ?? '#009ac7',
            ])
            ->values();
    }
}
