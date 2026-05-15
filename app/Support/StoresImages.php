<?php

namespace App\Support;

use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

trait StoresImages
{
    /**
     * Upload an image and return only the URL (backward-compatible).
     */
    private function storeImage(UploadedFile $file, string $folder, array $cloudinaryOptions = []): string
    {
        return $this->storeImageWithMeta($file, $folder, $cloudinaryOptions)['url'];
    }

    /**
     * Upload an image and return ['url' => ..., 'public_id' => ...].
     * public_id is null when using local storage.
     */
    protected function storeImageWithMeta(UploadedFile $file, string $folder, array $cloudinaryOptions = []): array
    {
        if ($this->cloudinaryIsConfigured()) {
            $response = Cloudinary::uploadApi()->upload($file->getRealPath(), array_merge(
                ['folder' => $folder, 'fetch_format' => 'auto', 'quality' => 'auto'],
                $cloudinaryOptions
            ));

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
