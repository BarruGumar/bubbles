<?php

namespace App\Enums;

enum AnnouncementType: string
{
    case Info        = 'info';
    case Update      = 'update';
    case Warning     = 'warning';
    case Maintenance = 'maintenance';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
