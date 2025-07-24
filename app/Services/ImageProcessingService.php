<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use App\Models\MediaAttachment;

class ImageProcessingService
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Process multiple images with advanced compression and optimization.
     */
    public function processImages(array $images, string $type, array $options = [], array $altTexts = []): array
    {
        $results = [];
        
        foreach ($images as $index => $image) {
            try {
                $result = $this->processSingleImage(
                    $image, 
                    $type, 
                    $options,
                    $altTexts[$index] ?? null
                );
                $results[] = $result;
            } catch (\Exception $e) {
                throw new \Exception("Failed to process image {$index}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Process a single image with all optimizations.
     */
    public function processSingleImage(UploadedFile $image, string $type, array $options = [], ?string $altText = null): array
    {
        // Get image dimensions and info
        $imageInfo = $this->getImageInfo($image);
        
        // Determine processing settings based on type and options
        $settings = $this->getProcessingSettings($type, $options, $imageInfo);
        
        // Generate filename and paths
        $filename = $this->generateFilename($image, $type);
        $disk = $this->getDiskForType($type);
        
        // Process image variants
        $variants = $this->createImageVariants($image, $settings, $disk, $filename);
        
        // Create MediaAttachment record
        $mediaAttachment = $this->createMediaAttachment([
            'user_id' => Auth::id(),
            'filename' => $image->getClientOriginalName(),
            'disk' => $disk,
            'path' => $variants['original']['path'],
            'url' => $variants['original']['url'],
            'mime_type' => $image->getMimeType(),
            'size' => $image->getSize(),
            'extension' => $image->getClientOriginalExtension(),
            'alt_text' => $altText,
            'type' => 'image',
            'width' => $imageInfo['width'],
            'height' => $imageInfo['height'],
            'variants' => $this->formatVariantsForDatabase($variants),
            'status' => 'processing',
            'is_safe' => true, // Could be enhanced with content analysis
            'is_public' => $this->isPublicType($type),
        ]);

        // Queue background processing for heavy operations
        if ($this->shouldUseBackgroundProcessing($imageInfo, $settings)) {
            // ProcessImageJob::dispatch($mediaAttachment, $settings); // Will create this job later
            $this->processImageOptimizations($mediaAttachment, $settings);
        } else {
            // Process immediately for small images
            $this->processImageOptimizations($mediaAttachment, $settings);
        }

        return [
            'media_attachment' => $mediaAttachment,
            'variants' => $variants,
            'processing' => $this->shouldUseBackgroundProcessing($imageInfo, $settings),
        ];
    }

    /**
     * Create multiple variants of an image.
     */
    protected function createImageVariants(UploadedFile $image, array $settings, string $disk, string $filename): array
    {
        $variants = [];
        $img = $this->imageManager->read($image->getRealPath());
        
        foreach ($settings['variants'] as $variantName => $variantSettings) {
            $variantImg = clone $img;
            $variantPath = $this->getVariantPath($filename, $variantName);
            
            // Apply transformations
            if ($variantSettings['resize']) {
                $variantImg = $this->resizeImage($variantImg, $variantSettings);
            }
            
            if ($variantSettings['optimize']) {
                $variantImg = $this->optimizeImage($variantImg, $variantSettings);
            }
            
            if ($variantSettings['watermark'] && config('app.watermark_enabled')) {
                $variantImg = $this->addWatermark($variantImg, $variantSettings);
            }
            
            // Convert and compress
            $processedData = $this->encodeImage($variantImg, $variantSettings);
            
            // Store to disk
            Storage::disk($disk)->put($variantPath, $processedData);
            
            $variants[$variantName] = [
                'path' => $variantPath,
                'url' => Storage::disk($disk)->url($variantPath),
                'width' => $variantImg->width(),
                'height' => $variantImg->height(),
                'size' => strlen($processedData),
                'quality' => $variantSettings['quality'],
            ];
        }
        
        return $variants;
    }

    /**
     * Resize image with smart cropping and aspect ratio handling.
     */
    protected function resizeImage($img, array $settings)
    {
        $width = $settings['width'];
        $height = $settings['height'];
        $method = $settings['resize_method'] ?? 'fit';
        
        return match ($method) {
            'fit' => $img->scale(width: $width, height: $height),
            'cover' => $img->cover($width, $height),
            'stretch' => $img->resize($width, $height),
            'crop' => $img->crop($width, $height),
            default => $img->scale(width: $width, height: $height),
        };
    }

    /**
     * Optimize image with advanced algorithms.
     */
    protected function optimizeImage($img, array $settings)
    {
        // Apply sharpening for resized images
        if ($settings['sharpen'] ?? false) {
            $img->sharpen(10);
        }
        
        // Adjust brightness and contrast if needed
        if (isset($settings['brightness'])) {
            $img->brightness($settings['brightness']);
        }
        
        if (isset($settings['contrast'])) {
            $img->contrast($settings['contrast']);
        }
        
        // Auto-enhance (basic level adjustment)
        if ($settings['auto_enhance'] ?? false) {
            $img->contrast(5);
        }
        
        return $img;
    }

    /**
     * Add watermark to image.
     */
    protected function addWatermark($img, array $settings)
    {
        $watermarkPath = config('app.watermark_path');
        if (!$watermarkPath || !file_exists($watermarkPath)) {
            return $img;
        }
        
        $watermark = $this->imageManager->read($watermarkPath);
        $position = $settings['watermark_position'] ?? 'bottom-right';
        $opacity = $settings['watermark_opacity'] ?? 50;
        
        // Scale watermark to 10% of image width
        $watermarkWidth = (int) ($img->width() * 0.1);
        $watermark->scale($watermarkWidth);
        
        // Position watermark
        [$x, $y] = $this->getWatermarkPosition($img, $watermark, $position);
        
        $img->place($watermark, 'top-left', $x, $y, $opacity);
        
        return $img;
    }

    /**
     * Encode image with optimal compression.
     */
    protected function encodeImage($img, array $settings): string
    {
        $quality = $settings['quality'];
        $format = $settings['format'] ?? 'jpg';
        
        return match ($format) {
            'webp' => $img->toWebp($quality)->toString(),
            'png' => $img->toPng()->toString(),
            'gif' => $img->toGif()->toString(),
            default => $img->toJpeg($quality)->toString(),
        };
    }

    /**
     * Get processing settings based on type and options.
     */
    protected function getProcessingSettings(string $type, array $options, array $imageInfo): array
    {
        $baseSettings = $this->getBaseSettings($type);
        
        // Merge with user options
        $settings = array_merge($baseSettings, $options);
        
        // Adjust quality based on original image size
        $settings = $this->adjustQualityForSize($settings, $imageInfo);
        
        // Determine variants to create
        $settings['variants'] = $this->determineVariants($type, $settings, $imageInfo);
        
        return $settings;
    }

    /**
     * Get base processing settings for each type.
     */
    protected function getBaseSettings(string $type): array
    {
        return match ($type) {
            'avatars' => [
                'quality' => 85,
                'format' => 'jpg',
                'auto_enhance' => true,
                'sharpen' => true,
                'watermark' => false,
            ],
            'covers' => [
                'quality' => 80,
                'format' => 'jpg',
                'auto_enhance' => true,
                'sharpen' => false,
                'watermark' => false,
            ],
            'posts' => [
                'quality' => 85,
                'format' => 'jpg',
                'auto_enhance' => false,
                'sharpen' => false,
                'watermark' => true,
            ],
            'groups' => [
                'quality' => 80,
                'format' => 'jpg',
                'auto_enhance' => true,
                'sharpen' => false,
                'watermark' => false,
            ],
            'messages' => [
                'quality' => 75,
                'format' => 'jpg',
                'auto_enhance' => false,
                'sharpen' => false,
                'watermark' => false,
            ],
            default => [
                'quality' => 80,
                'format' => 'jpg',
                'auto_enhance' => false,
                'sharpen' => false,
                'watermark' => false,
            ],
        };
    }

    /**
     * Determine which variants to create.
     */
    protected function determineVariants(string $type, array $settings, array $imageInfo): array
    {
        $variants = ['original' => [
            'resize' => false,
            'optimize' => true,
            'watermark' => $settings['watermark'] ?? false,
            'quality' => $settings['quality'],
            'format' => $settings['format'],
        ]];
        
        $variantSpecs = match ($type) {
            'avatars' => [
                'large' => ['width' => 400, 'height' => 400],
                'medium' => ['width' => 200, 'height' => 200],
                'small' => ['width' => 100, 'height' => 100],
            ],
            'covers' => [
                'large' => ['width' => 1200, 'height' => 400],
                'medium' => ['width' => 800, 'height' => 267],
                'small' => ['width' => 400, 'height' => 133],
            ],
            'posts' => [
                'large' => ['width' => 1200, 'height' => null],
                'medium' => ['width' => 800, 'height' => null],
                'thumbnail' => ['width' => 300, 'height' => 300],
            ],
            'groups' => [
                'large' => ['width' => 800, 'height' => 400],
                'medium' => ['width' => 400, 'height' => 200],
                'thumbnail' => ['width' => 150, 'height' => 150],
            ],
            'messages' => [
                'medium' => ['width' => 600, 'height' => null],
                'thumbnail' => ['width' => 200, 'height' => 200],
            ],
            default => [
                'large' => ['width' => 800, 'height' => null],
                'thumbnail' => ['width' => 300, 'height' => 300],
            ],
        };
        
        foreach ($variantSpecs as $variantName => $spec) {
            // Only create variant if original is larger
            if ($spec['width'] && $imageInfo['width'] > $spec['width']) {
                $variants[$variantName] = array_merge($variants['original'], [
                    'resize' => true,
                    'width' => $spec['width'],
                    'height' => $spec['height'],
                    'resize_method' => $spec['height'] ? 'cover' : 'fit',
                    'quality' => max(70, $settings['quality'] - 10), // Lower quality for variants
                ]);
            }
        }
        
        return $variants;
    }

    /**
     * Adjust quality based on image size for optimal compression.
     */
    protected function adjustQualityForSize(array $settings, array $imageInfo): array
    {
        $pixelCount = $imageInfo['width'] * $imageInfo['height'];
        
        // Lower quality for very large images to reduce file size
        if ($pixelCount > 4000000) { // > 4MP
            $settings['quality'] = min($settings['quality'], 75);
        } elseif ($pixelCount > 2000000) { // > 2MP
            $settings['quality'] = min($settings['quality'], 80);
        }
        
        return $settings;
    }

    /**
     * Get image information.
     */
    protected function getImageInfo(UploadedFile $image): array
    {
        $imageSize = getimagesize($image->getRealPath());
        
        return [
            'width' => $imageSize[0],
            'height' => $imageSize[1],
            'mime_type' => $imageSize['mime'],
            'channels' => $imageSize['channels'] ?? null,
            'bits' => $imageSize['bits'] ?? null,
        ];
    }

    /**
     * Generate unique filename.
     */
    protected function generateFilename(UploadedFile $image, string $type): string
    {
        $extension = $image->getClientOriginalExtension();
        $timestamp = now()->format('Y/m/d');
        $uuid = Str::uuid();
        
        return "{$type}/{$timestamp}/{$uuid}.{$extension}";
    }

    /**
     * Get disk for storage type.
     */
    protected function getDiskForType(string $type): string
    {
        $useCloud = config('app.env') === 'production' && config('filesystems.cloud');
        
        if ($useCloud) {
            return match ($type) {
                'avatars', 'covers' => 's3-avatars',
                'posts' => 's3-posts',
                'groups' => 's3-groups',
                'messages' => 's3-messages',
                default => 's3',
            };
        }
        
        return match ($type) {
            'avatars', 'covers' => 'avatars',
            'posts' => 'posts',
            'groups' => 'groups',
            'messages' => 'messages',
            default => 'public',
        };
    }

    /**
     * Get variant path.
     */
    protected function getVariantPath(string $filename, string $variant): string
    {
        if ($variant === 'original') {
            return $filename;
        }
        
        $pathInfo = pathinfo($filename);
        return $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $variant . '.' . $pathInfo['extension'];
    }

    /**
     * Create MediaAttachment record.
     */
    protected function createMediaAttachment(array $data): MediaAttachment
    {
        return MediaAttachment::create($data);
    }

    /**
     * Format variants for database storage.
     */
    protected function formatVariantsForDatabase(array $variants): array
    {
        $formatted = [];
        
        foreach ($variants as $name => $variant) {
            if ($name !== 'original') {
                $formatted[$name] = [
                    'path' => $variant['path'],
                    'width' => $variant['width'],
                    'height' => $variant['height'],
                    'size' => $variant['size'],
                ];
            }
        }
        
        return $formatted;
    }

    /**
     * Check if type should be public.
     */
    protected function isPublicType(string $type): bool
    {
        return in_array($type, ['avatars', 'covers', 'posts', 'groups']);
    }

    /**
     * Check if background processing should be used.
     */
    protected function shouldUseBackgroundProcessing(array $imageInfo, array $settings): bool
    {
        $pixelCount = $imageInfo['width'] * $imageInfo['height'];
        return $pixelCount > 2000000 || count($settings['variants']) > 3;
    }

    /**
     * Process image optimizations (called by job or immediately).
     */
    public function processImageOptimizations(MediaAttachment $mediaAttachment, array $settings): void
    {
        try {
            // Mark as ready
            $mediaAttachment->update(['status' => 'ready']);
            
            // Additional post-processing could go here
            // (e.g., metadata extraction, content analysis)
            
        } catch (\Exception $e) {
            $mediaAttachment->update([
                'status' => 'failed',
                'analysis_results' => ['error' => $e->getMessage()],
            ]);
            
            throw $e;
        }
    }

    /**
     * Get watermark position coordinates.
     */
    protected function getWatermarkPosition($img, $watermark, string $position): array
    {
        $padding = 20;
        
        return match ($position) {
            'top-left' => [$padding, $padding],
            'top-right' => [$img->width() - $watermark->width() - $padding, $padding],
            'bottom-left' => [$padding, $img->height() - $watermark->height() - $padding],
            'bottom-right' => [
                $img->width() - $watermark->width() - $padding,
                $img->height() - $watermark->height() - $padding
            ],
            'center' => [
                ($img->width() - $watermark->width()) / 2,
                ($img->height() - $watermark->height()) / 2
            ],
            default => [
                $img->width() - $watermark->width() - $padding,
                $img->height() - $watermark->height() - $padding
            ],
        };
    }
} 