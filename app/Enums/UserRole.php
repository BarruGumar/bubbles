<?php

namespace App\Enums;

enum UserRole: string
{
    case SiteOwner = 'site_owner';
    case Admin     = 'admin';
    case Moderator = 'moderator';
    case User      = 'user';
    case Suspended = 'suspended';
    case Banned    = 'banned';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Roles assignable by a non-site_owner admin (excludes site_owner). */
    public static function assignableByAdmin(): array
    {
        return [
            self::User->value,
            self::Moderator->value,
            self::Admin->value,
            self::Suspended->value,
            self::Banned->value,
        ];
    }
}
