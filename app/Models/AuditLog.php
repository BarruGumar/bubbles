<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'action', 'category',
        'target_type', 'target_id', 'target_user_id', 'community_id',
        'ip_address', 'user_agent', 'method', 'route_name', 'url',
        'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Bubble::class, 'community_id');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('actor_id', $userId)->orWhere('target_user_id', $userId);
    }

    public function scopeForCommunity(Builder $query, int $communityId): Builder
    {
        return $query->where('community_id', $communityId);
    }

    public function scopeOfCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }
}
