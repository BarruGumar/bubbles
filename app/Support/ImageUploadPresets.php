<?php

namespace App\Support;

class ImageUploadPresets
{
    public static function post(): array
    {
        return [
            'transformation' => [
                'width'   => 1280,
                'height'  => 960,
                'crop'    => 'limit',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function message(): array
    {
        return [
            'transformation' => [
                'width'   => 1280,
                'height'  => 960,
                'crop'    => 'limit',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function gif(): array
    {
        return [
            'transformation' => [
                'width'   => 600,
                'height'  => 600,
                'crop'    => 'limit',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function conversationBackground(): array
    {
        return [
            'transformation' => [
                'width'   => 1920,
                'height'  => 1080,
                'crop'    => 'limit',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function avatar(int $userId): array
    {
        return [
            'public_id'      => 'user_' . $userId,
            'overwrite'      => true,
            'transformation' => [
                'width'   => 300,
                'height'  => 300,
                'crop'    => 'fill',
                'gravity' => 'face',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function profileBanner(int $userId): array
    {
        return [
            'public_id'      => 'banner_' . $userId,
            'overwrite'      => true,
            'transformation' => [
                'width'   => 1200,
                'height'  => 400,
                'crop'    => 'fill',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function communityImage(int $communityId): array
    {
        return [
            'public_id'      => 'community_img_' . $communityId,
            'overwrite'      => true,
            'transformation' => [
                'width'   => 300,
                'height'  => 300,
                'crop'    => 'fill',
                'quality' => 'auto:good',
            ],
        ];
    }

    public static function communityBanner(int $communityId): array
    {
        return [
            'public_id'      => 'community_banner_' . $communityId,
            'overwrite'      => true,
            'transformation' => [
                'width'   => 1400,
                'height'  => 500,
                'crop'    => 'fill',
                'quality' => 'auto:good',
            ],
        ];
    }
}
