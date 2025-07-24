<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class StorageService
{
    /**
     * Upload a file to the specified disk
     */
    public function uploadFile(UploadedFile $file, string $type = 'public', ?string $folder = null): array
    {
        $disk = $this->getDiskForType($type);
        $filename = $this->generateUniqueFilename($file);
        $path = $folder ? $folder . '/' . $filename : $filename;

        // Store the file
        $storedPath = $file->storeAs('', $path, $disk);

        return [
            'disk' => $disk,
            'path' => $storedPath,
            'url' => Storage::disk($disk)->url($storedPath),
            'size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'original_name' => $file->getClientOriginalName(),
        ];
    }

    /**
     * Upload and process an image
     */
    public function uploadImage(UploadedFile $image, string $type = 'avatars', ?array $sizes = null): array
    {
        $this->validateImage($image);
        
        $disk = $this->getDiskForType($type);
        $filename = $this->generateUniqueFilename($image);
        
        $results = [];
        
        // Default sizes for social media images
        $defaultSizes = [
            'original' => null,
            'large' => [800, 600],
            'medium' => [400, 300],
            'thumbnail' => [150, 150],
        ];
        
        $imageSizes = $sizes ?? $defaultSizes;
        
        foreach ($imageSizes as $sizeName => $dimensions) {
            $processedImage = $this->processImage($image, $dimensions);
            $path = $sizeName === 'original' ? $filename : $sizeName . '_' . $filename;
            
            // Store the processed image
            Storage::disk($disk)->put($path, $processedImage);
            
            $results[$sizeName] = [
                'disk' => $disk,
                'path' => $path,
                'url' => Storage::disk($disk)->url($path),
                'width' => $dimensions[0] ?? null,
                'height' => $dimensions[1] ?? null,
            ];
        }
        
        return $results;
    }

    /**
     * Delete a file from storage
     */
    public function deleteFile(string $disk, string $path): bool
    {
        return Storage::disk($disk)->delete($path);
    }

    /**
     * Move files to cloud storage
     */
    public function migrateToCloud(string $localDisk, string $cloudDisk, string $path): array
    {
        if (!Storage::disk($localDisk)->exists($path)) {
            throw new \Exception("File not found: {$path}");
        }

        $contents = Storage::disk($localDisk)->get($path);
        
        // Store to cloud
        Storage::disk($cloudDisk)->put($path, $contents);
        
        // Verify upload
        if (!Storage::disk($cloudDisk)->exists($path)) {
            throw new \Exception("Failed to upload to cloud storage");
        }
        
        // Delete local file
        Storage::disk($localDisk)->delete($path);
        
        return [
            'from_disk' => $localDisk,
            'to_disk' => $cloudDisk,
            'path' => $path,
            'url' => Storage::disk($cloudDisk)->url($path),
        ];
    }

    /**
     * Get the appropriate disk for a file type
     */
    protected function getDiskForType(string $type): string
    {
        // Check if cloud storage is available and should be used
        if ($this->shouldUseCloudStorage()) {
            return match($type) {
                'avatars' => 's3-avatars',
                'posts' => 's3-posts',
                'messages' => 's3-messages',
                'groups' => 's3-groups',
                default => 's3',
            };
        }

        // Use local storage
        return match($type) {
            'avatars' => 'avatars',
            'posts' => 'posts',
            'messages' => 'messages',
            'groups' => 'groups',
            'temp' => 'temp',
            'secure' => 'secure',
            default => 'public',
        };
    }

    /**
     * Check if cloud storage should be used
     */
    protected function shouldUseCloudStorage(): bool
    {
        return config('app.env') === 'production' && 
               config('filesystems.disks.s3.key') && 
               config('filesystems.disks.s3.secret');
    }

    /**
     * Generate a unique filename
     */
    protected function generateUniqueFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $timestamp = now()->format('Y/m/d');
        $uuid = Str::uuid();
        
        return "{$timestamp}/{$uuid}.{$extension}";
    }

    /**
     * Validate uploaded image
     */
    protected function validateImage(UploadedFile $image): void
    {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 10 * 1024 * 1024; // 10MB
        
        if (!in_array($image->getMimeType(), $allowedMimes)) {
            throw new \Exception('Invalid image format. Allowed: JPEG, PNG, GIF, WebP');
        }
        
        if ($image->getSize() > $maxSize) {
            throw new \Exception('Image too large. Maximum size: 10MB');
        }
    }

    /**
     * Process image with optional resizing
     */
    protected function processImage(UploadedFile $image, ?array $dimensions = null): string
    {
        $manager = new ImageManager(new Driver());
        $img = $manager->read($image->getRealPath());
        
        if ($dimensions) {
            [$width, $height] = $dimensions;
            $img->cover($width, $height);
        }
        
        // Encode as JPEG with quality optimization
        return $img->toJpeg(85)->toString();
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $disk, string $path): array
    {
        $storage = Storage::disk($disk);
        
        return [
            'exists' => $storage->exists($path),
            'size' => $storage->exists($path) ? $storage->size($path) : null,
            'last_modified' => $storage->exists($path) ? $storage->lastModified($path) : null,
            'url' => $storage->url($path),
            'mime_type' => $storage->exists($path) ? $storage->mimeType($path) : null,
        ];
    }

    /**
     * Create a temporary URL for private files
     */
    public function createTemporaryUrl(string $disk, string $path, int $expiresInMinutes = 60): string
    {
        return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes($expiresInMinutes));
    }
} 