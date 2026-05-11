<?php

namespace App\Support;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;

trait StoresImages
{
    private function storeImage(UploadedFile $file, string $folder, array $cloudinaryOptions = []): string
    {
        if ($this->cloudinaryIsConfigured()) {
            $response = Cloudinary::uploadApi()->upload($file->getRealPath(), array_merge(
                ['folder' => $folder, 'fetch_format' => 'auto', 'quality' => 'auto'],
                $cloudinaryOptions
            ));
            return $response['secure_url'];
        }

        $path = $file->store($folder, 'public');

        return '/storage/' . $path;
    }

    private function cloudinaryIsConfigured(): bool
    {
        $disk = config('filesystems.disks.cloudinary', []);
        $url = $disk['url'] ?? null;

        if (is_string($url) && $this->hasRealCloudinaryValue($url)) {
            return true;
        }

        return $this->hasRealCloudinaryValue($disk['cloud'] ?? null)
            && $this->hasRealCloudinaryValue($disk['key'] ?? null)
            && $this->hasRealCloudinaryValue($disk['secret'] ?? null);
    }

    private function hasRealCloudinaryValue(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        $value = trim($value);

        return $value !== ''
            && ! str_contains($value, 'CLOUD_NAME')
            && ! str_contains($value, 'API_KEY')
            && ! str_contains($value, 'API_SECRET');
    }
}