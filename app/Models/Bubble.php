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
    ];

    protected $casts = [
        'x' => 'float',
        'y' => 'float',
        'size' => 'integer',
        'members' => 'array',
    ];

    public function outgoingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'from_bubble_id');
    }

    public function incomingConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'to_bubble_id');
    }
}