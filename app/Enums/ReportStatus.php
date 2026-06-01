<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Pending   = 'pending';
    case Resolved  = 'resolved';
    case Dismissed = 'dismissed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
