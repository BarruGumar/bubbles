<?php

namespace App\Enums;

enum CommunityMemberStatus: string
{
    case Active = 'active';
    case Banned = 'banned';
    case Muted  = 'muted';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
