<?php

namespace App\Enums;

enum CommunityMemberRole: string
{
    case Owner     = 'owner';
    case Admin     = 'admin';
    case Moderator = 'moderator';
    case Member    = 'member';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
