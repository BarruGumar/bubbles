<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CommunityMembership extends Pivot
{
    protected $table = 'community_user';

    protected $casts = [
        'banned_at'   => 'datetime',
        'banned_until' => 'datetime',
        'muted_until'  => 'datetime',
        'joined_at'    => 'datetime',
    ];

    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public function mutedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'muted_by');
    }

    public function isActiveBan(): bool
    {
        if ($this->status !== 'banned') {
            return false;
        }

        return $this->banned_until === null || $this->banned_until->isFuture();
    }

    public function isActiveMute(): bool
    {
        if ($this->status !== 'muted') {
            return false;
        }

        return $this->muted_until === null || $this->muted_until->isFuture();
    }
}
