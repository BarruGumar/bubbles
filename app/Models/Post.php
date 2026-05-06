<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    protected $fillable = ['content', 'user_id', 'image'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
