<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bubble extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'color',
        'x',
        'y',
        'size',
        'members',
        'community_title',
        'community_description',
        'community_cover_color',
        'community_tagline',
        'community_guidelines',
        'community_image',
        'community_banner',
    ];

    protected $casts = [
        'x'       => 'float',
        'y'       => 'float',
        'size'    => 'integer',
        'members' => 'integer',
        'community_guidelines' => 'array',
    ];

    public function communityPosts(): HasMany
    {
        return $this->hasMany(CommunityPost::class);
    }

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'from_bubble_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'to_bubble_id');
    }
}
