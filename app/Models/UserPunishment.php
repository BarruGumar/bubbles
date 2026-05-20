<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPunishment extends Model
{
    protected $fillable = [
        'user_id', 'issued_by', 'type', 'reason', 'notes',
        'starts_at', 'ends_at',
        'revoked_at', 'revoked_by', 'revoked_reason',
        'notified_at',
    ];

    protected $casts = [
        'starts_at'   => 'datetime',
        'ends_at'     => 'datetime',
        'revoked_at'  => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function isActive(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast() && $this->revoked_at === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }
}
