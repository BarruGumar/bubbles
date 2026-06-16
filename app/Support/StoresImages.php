<?php

namespace App\Support;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Support\Facades\Log;

trait StoresImages
{
    /**
     * Upload a video and return ['url' => ..., 'public_id' => ...].
     */
    protected function storeVideoWithMeta(UploadedFile $file, string $folder): array
    {
        if ($this->cloudinaryIsConfigured()) {
            $response = Cloudinary::uploadApi()->upload($file->getRealPath(), [
                'folder' => $folder,
                'resource_type' => 'video',
            ]);

            return [
                'url' => $response['secure_url'],
                'public_id' => $response['public_id'],
            ];
        }

        $path = $file->store($folder, 'public');

        return [
            'url' => '/storage/'.$path,
            'public_id' => null,
        ];
    }

    /**
     * Delete a video from Cloudinary by public_id.
     */
    protected function deleteCloudinaryVideo(?string $publicId): void
    {
        if (! $publicId || ! $this->cloudinaryIsConfigured()) {
            return;
        }

        try {
            Cloudinary::uploadApi()->destroy($publicId, ['resource_type' => 'video']);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary video delete failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete an image from Cloudinary by public_id.
     * Silently ignored when Cloudinary is not configured or public_id is null.
     * Never throws — logs on failure so the app keeps working.
     */
    protected function deleteCloudinaryImage(?string $publicId): void
    {
        if (! $publicId || ! $this->cloudinaryIsConfigured()) {
            return;
        }

        try {
            Cloudinary::uploadApi()->destroy($publicId);
        } catch (\Throwable $e) {
            Log::warning('Cloudinary delete failed', [
                'public_id' => $publicId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function validateCloudinaryUrl(string $url): void
    {
        abort_unless(
            str_starts_with($url, 'https://res.cloudinary.com/'),
            422,
            'URL de imagem inválido.'
        );
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
