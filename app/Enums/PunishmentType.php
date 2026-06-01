<?php

namespace App\Enums;

enum PunishmentType: string
{
    case Warning    = 'warning';
    case Mute       = 'mute';
    case Suspension = 'suspension';
    case Ban        = 'ban';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
