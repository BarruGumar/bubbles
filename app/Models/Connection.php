<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Connection extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_bubble_id',
        'to_bubble_id',
    ];

    public function fromBubble(): BelongsTo
    {
        return $this->belongsTo(Bubble::class, 'from_bubble_id');
    }

    public function toBubble(): BelongsTo
    {
        return $this->belongsTo(Bubble::class, 'to_bubble_id');
    }
}
