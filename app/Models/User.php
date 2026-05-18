<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'username', 'bio',
        'avatar_color', 'avatar', 'avatar_public_id',
        'banner', 'banner_public_id', 'theme',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Global role helpers ───────────────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return in_array($this->role, ['admin', 'moderator']);
    }

    /** Role-based suspension OR active suspension punishment */
    public function isSuspended(): bool
    {
        if ($this->role === 'suspended') {
            return true;
        }

        return $this->punishments()
            ->active()
            ->ofType('suspension')
            ->exists();
    }

    /** Active global ban via user_punishments */
    public function isBanned(): bool
    {
        return $this->punishments()
            ->active()
            ->ofType('ban')
            ->exists();
    }

    /** Active global mute via user_punishments */
    public function isGloballyMuted(): bool
    {
        return $this->punishments()
            ->active()
            ->ofType('mute')
            ->exists();
    }

    public function canPost(): bool
    {
        return ! $this->isBanned() && ! $this->isSuspended() && ! $this->isGloballyMuted();
    }

    public function canComment(): bool
    {
        return ! $this->isBanned() && ! $this->isSuspended() && ! $this->isGloballyMuted();
    }

    public function canMessage(): bool
    {
        return ! $this->isBanned() && ! $this->isSuspended() && ! $this->isGloballyMuted();
    }

    // ── Community role helpers ────────────────────────────────────

    /**
     * Returns the user's role in a community:
     * 'owner' | 'admin' | 'moderator' | 'member' | 'banned' | 'none'
     */
    public function communityRole(Bubble $bubble): string
    {
        if ($this->id === $bubble->user_id) {
            return 'owner';
        }

        $pivot = $this->communities()
            ->where('community_id', $bubble->id)
            ->first()?->pivot;

        if (! $pivot) {
            return 'none';
        }

        if ($pivot->status === 'banned') {
            return 'banned';
        }

        return $pivot->role ?? 'member';
    }

    public function canManageCommunity(Bubble $bubble): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($this->communityRole($bubble), ['owner', 'admin']);
    }

    public function canModerateCommunity(Bubble $bubble): bool
    {
        if ($this->isModerator()) {
            return true;
        }

        return in_array($this->communityRole($bubble), ['owner', 'admin', 'moderator']);
    }

    public function isBannedFromCommunity(Bubble $bubble): bool
    {
        $pivot = $this->communities()
            ->where('community_id', $bubble->id)
            ->first()?->pivot;

        if (! $pivot || $pivot->status !== 'banned') {
            return false;
        }

        // Permanent ban or still within ban window
        return $pivot->banned_until === null || $pivot->banned_until->isFuture();
    }

    public function isMutedInCommunity(Bubble $bubble): bool
    {
        $pivot = $this->communities()
            ->where('community_id', $bubble->id)
            ->first()?->pivot;

        if (! $pivot || $pivot->status !== 'muted') {
            return false;
        }

        return $pivot->muted_until === null || $pivot->muted_until->isFuture();
    }

    // ── Punishments ───────────────────────────────────────────────

    public function punishments(): HasMany
    {
        return $this->hasMany(UserPunishment::class);
    }

    public function issuedPunishments(): HasMany
    {
        return $this->hasMany(UserPunishment::class, 'issued_by');
    }

    public function activePunishments()
    {
        return $this->punishments()->active()->get();
    }

    // ── Relations ─────────────────────────────────────────────────

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function communities(): BelongsToMany
    {
        return $this->belongsToMany(Bubble::class, 'community_user', 'user_id', 'community_id')
            ->using(CommunityMembership::class)
            ->withPivot([
                'role', 'status', 'joined_at',
                'banned_at', 'banned_until', 'banned_by', 'ban_reason',
                'muted_until', 'muted_by', 'mute_reason',
            ])
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friend::class, 'user_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friend::class, 'friend_id');
    }
}
