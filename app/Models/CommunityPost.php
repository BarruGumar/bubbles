<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityPost extends Model
{
    protected $fillable = ['bubble_id', 'user_id', 'content'];

    public function bubble(): BelongsTo
    {
        return $this->belongsTo(Bubble::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
