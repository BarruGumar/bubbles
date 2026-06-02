<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBlock extends Model
{
    public $timestamps = false;

    protected $fillable = ['blocker_id', 'blocked_id'];

    public function blocker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocker_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_id');
    }

    public static function mutualIds(int $userId): array
    {
        return self::where('blocker_id', $userId)->pluck('blocked_id')
            ->merge(self::where('blocked_id', $userId)->pluck('blocker_id'))
            ->unique()
            ->all();
    }
}
