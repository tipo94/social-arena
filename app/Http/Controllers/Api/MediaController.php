<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageService;
use App\Services\ImageProcessingService;
use App\Services\VideoProcessingService;
use App\Http\Requests\ImageUploadRequest;
use App\Http\Requests\VideoUploadRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected ImageProcessingService $imageProcessingService,
        protected VideoProcessingService $videoProcessingService
    ) {}

    /**
     * Upload images with advanced processing and compression
     */
    public function uploadImages(ImageUploadRequest $request): JsonResponse
    {
        try {
            $data = $request->getProcessedData();
            
            $results = $this->imageProcessingService->processImages(
                $data['images'],
                $data['type'],
                $data['options'],
                $data['alt_texts']
            );

            return response()->json([
                'success' => true,
                'message' => count($data['images']) > 1 ? 'Images uploaded successfully' : 'Image uploaded successfully',
                'data' => [
                    'uploads' => $results,
                    'count' => count($results),
                    'processing_in_background' => collect($results)->some(fn($result) => $result['processing']),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload a single image (legacy endpoint for backward compatibility)
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB max
            'type' => 'required|in:avatars,posts,messages,groups',
            'sizes' => 'sometimes|array',
        ]);

        try {
            $results = $this->storageService->uploadImage(
                $request->file('image'),
                $request->input('type'),
                $request->input('sizes')
            );

            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload image',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Upload a file
     */
    public function uploadFile(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:50240', // 50MB max
            'type' => 'required|in:public,messages,groups,temp',
            'folder' => 'sometimes|string|max:100',
        ]);

        try {
            $result = $this->storageService->uploadFile(
                $request->file('file'),
                $request->input('type'),
                $request->input('folder')
            );

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get file information
     */
    public function getFileInfo(Request $request): JsonResponse
    {
        $request->validate([
            'disk' => 'required|string',
            'path' => 'required|string',
        ]);

        try {
            $info = $this->storageService->getFileInfo(
                $request->input('disk'),
                $request->input('path')
            );

            return response()->json([
                'success' => true,
                'data' => $info,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get file information',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create temporary URL for private files
     */
    public function createTemporaryUrl(Request $request): JsonResponse
    {
        $request->validate([
            'disk' => 'required|string',
            'path' => 'required|string',
            'expires_in_minutes' => 'sometimes|integer|min:1|max:1440', // Max 24 hours
        ]);

        try {
            $url = $this->storageService->createTemporaryUrl(
                $request->input('disk'),
                $request->input('path'),
                $request->input('expires_in_minutes', 60)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url,
                    'expires_in_minutes' => $request->input('expires_in_minutes', 60),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create temporary URL',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a file
     */
    public function deleteFile(Request $request): JsonResponse
    {
        $request->validate([
            'disk' => 'required|string',
            'path' => 'required|string',
        ]);

        try {
            $deleted = $this->storageService->deleteFile(
                $request->input('disk'),
                $request->input('path')
            );

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'File not found',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get processing status of a media attachment
     */
    public function getProcessingStatus(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this media',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $media->id,
                    'status' => $media->status,
                    'is_ready' => $media->isReady(),
                    'variants' => $media->variants,
                    'analysis_results' => $media->analysis_results,
                    'processing_progress' => $this->getProcessingProgress($media),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get processing status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Compress existing image
     */
    public function compressImage(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
            'quality' => 'sometimes|integer|min:10|max:100',
            'max_width' => 'sometimes|integer|min:100|max:4000',
            'max_height' => 'sometimes|integer|min:100|max:4000',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to modify this media',
                ], 403);
            }

            if (!$media->isImage()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only images can be compressed',
                ], 422);
            }

            // Create compression options
            $options = [
                'quality' => $request->input('quality', 80),
                'max_width' => $request->input('max_width'),
                'max_height' => $request->input('max_height'),
                'compress' => true,
            ];

            // Reprocess with new settings
            \App\Jobs\ProcessImageJob::dispatch($media, ['options' => $options]);

            return response()->json([
                'success' => true,
                'message' => 'Image compression started',
                'data' => [
                    'media_id' => $media->id,
                    'status' => 'processing',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to compress image',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get processing progress estimate
     */
    protected function getProcessingProgress(\App\Models\MediaAttachment $media): array
    {
        if ($media->status === 'ready') {
            return ['progress' => 100, 'stage' => 'completed'];
        }

        if ($media->status === 'failed') {
            return ['progress' => 0, 'stage' => 'failed'];
        }

        // Estimate progress based on variants
        $totalVariants = count($media->variants ?? []) + 1; // +1 for original
        $readyVariants = 0;

        if ($media->variants) {
            foreach ($media->variants as $variant) {
                if (isset($variant['path']) && Storage::disk($media->disk)->exists($variant['path'])) {
                    $readyVariants++;
                }
            }
        }

        $progress = min(90, ($readyVariants / $totalVariants) * 100);

        return [
            'progress' => $progress,
            'stage' => 'processing',
            'variants_ready' => $readyVariants,
            'total_variants' => $totalVariants,
        ];
    }

    /**
     * Upload videos with compression and format conversion
     */
    public function uploadVideos(VideoUploadRequest $request): JsonResponse
    {
        try {
            $data = $request->getProcessedData();
            
            $results = $this->videoProcessingService->processVideos(
                $data['videos'],
                $data['type'],
                array_merge($data['options'], $request->getQualitySettings())
            );

            return response()->json([
                'success' => true,
                'message' => count($data['videos']) > 1 ? 'Videos uploaded successfully' : 'Video uploaded successfully',
                'data' => [
                    'uploads' => $results,
                    'count' => count($results),
                    'processing_in_background' => true, // Videos always process in background
                    'estimated_completion_times' => array_map(fn($result) => $result['estimated_time'], $results),
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload videos',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get video processing status with detailed information
     */
    public function getVideoProcessingStatus(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this media',
                ], 403);
            }

            if ($media->type !== 'video') {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is for video processing status only',
                ], 422);
            }

            $progress = $this->getVideoProcessingProgress($media);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $media->id,
                    'filename' => $media->filename,
                    'status' => $media->status,
                    'is_ready' => $media->isReady(),
                    'duration' => $media->duration,
                    'variants' => $media->variants,
                    'analysis_results' => $media->analysis_results,
                    'processing_progress' => $progress,
                    'thumbnail_available' => isset($media->variants['thumbnail']),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get video processing status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Convert existing video to different format
     */
    public function convertVideo(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
            'output_format' => 'required|string|in:mp4,webm',
            'quality' => 'sometimes|string|in:low,medium,high,ultra',
            'max_resolution' => 'sometimes|string|regex:/^\d+x\d+$/',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to modify this media',
                ], 403);
            }

            if ($media->type !== 'video') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only videos can be converted',
                ], 422);
            }

            // Create conversion options
            $options = [
                'output_format' => $request->input('output_format'),
                'quality' => $request->input('quality', 'medium'),
                'max_resolution' => $request->input('max_resolution'),
            ];

            // Create a new MediaAttachment for the converted video
            $convertedMedia = \App\Models\MediaAttachment::create([
                'user_id' => $media->user_id,
                'filename' => pathinfo($media->filename, PATHINFO_FILENAME) . '_converted.' . $request->input('output_format'),
                'disk' => $media->disk,
                'path' => $media->path . '_converted',
                'mime_type' => 'video/' . $request->input('output_format'),
                'size' => $media->size, // Will be updated after conversion
                'extension' => $request->input('output_format'),
                'type' => 'video',
                'width' => $media->width,
                'height' => $media->height,
                'duration' => $media->duration,
                'status' => 'processing',
                'is_safe' => $media->is_safe,
                'is_public' => $media->is_public,
                'analysis_results' => [
                    'conversion_from' => $media->id,
                    'conversion_options' => $options,
                    'processing_started_at' => now()->toISOString(),
                ],
            ]);

            // Queue conversion job
            \App\Jobs\ProcessVideoJob::dispatch($convertedMedia, $options, $media->path);

            return response()->json([
                'success' => true,
                'message' => 'Video conversion started',
                'data' => [
                    'original_media_id' => $media->id,
                    'converted_media_id' => $convertedMedia->id,
                    'status' => 'processing',
                    'format' => $request->input('output_format'),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start video conversion',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Generate thumbnail for video at specific time
     */
    public function generateVideoThumbnail(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
            'time' => 'required|numeric|min:0', // Time in seconds
            'width' => 'sometimes|integer|min:100|max:1920',
            'height' => 'sometimes|integer|min:100|max:1080',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to modify this media',
                ], 403);
            }

            if ($media->type !== 'video') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only videos can have thumbnails generated',
                ], 422);
            }

            // Validate time is within video duration
            if ($media->duration && $request->input('time') > $media->duration) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thumbnail time cannot exceed video duration',
                ], 422);
            }

            // For now, return success but note that actual implementation would require FFmpeg
            return response()->json([
                'success' => true,
                'message' => 'Thumbnail generation queued (requires FFmpeg implementation)',
                'data' => [
                    'media_id' => $media->id,
                    'time' => $request->input('time'),
                    'status' => 'queued',
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate thumbnail',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get video metadata and technical information
     */
    public function getVideoMetadata(Request $request): JsonResponse
    {
        $request->validate([
            'media_id' => 'required|integer|exists:media_attachments,id',
        ]);

        try {
            $media = \App\Models\MediaAttachment::findOrFail($request->input('media_id'));

            // Check if user owns this media
            if ($media->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this media',
                ], 403);
            }

            if ($media->type !== 'video') {
                return response()->json([
                    'success' => false,
                    'message' => 'This endpoint is for videos only',
                ], 422);
            }

            $metadata = [
                'id' => $media->id,
                'filename' => $media->filename,
                'size' => $media->size,
                'formatted_size' => $media->formatted_size,
                'mime_type' => $media->mime_type,
                'extension' => $media->extension,
                'duration' => $media->duration,
                'width' => $media->width,
                'height' => $media->height,
                'resolution' => $media->width && $media->height ? "{$media->width}x{$media->height}" : null,
                'aspect_ratio' => $media->width && $media->height ? round($media->width / $media->height, 2) : null,
                'variants' => $media->variants,
                'analysis_results' => $media->analysis_results,
                'created_at' => $media->created_at->toISOString(),
                'status' => $media->status,
                'is_ready' => $media->isReady(),
                'compression_ratio' => $this->getCompressionRatio($media),
            ];

            return response()->json([
                'success' => true,
                'data' => $metadata,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get video metadata',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get video processing progress with detailed information.
     */
    protected function getVideoProcessingProgress(\App\Models\MediaAttachment $media): array
    {
        if ($media->status === 'ready') {
            return [
                'progress' => 100,
                'stage' => 'completed',
                'estimated_completion' => null,
            ];
        }

        if ($media->status === 'failed') {
            return [
                'progress' => 0,
                'stage' => 'failed',
                'error' => $media->analysis_results['error'] ?? 'Unknown error',
            ];
        }

        // Estimate progress based on processing time
        $startTime = $media->analysis_results['processing_started_at'] ?? null;
        if ($startTime) {
            $start = \Carbon\Carbon::parse($startTime);
            $elapsed = $start->diffInMinutes(now(), true);
            
            // Rough estimation based on video duration
            $estimatedTotal = ($media->duration ?? 60) * 3; // 3 minutes per minute of video
            $progress = min(90, ($elapsed / $estimatedTotal) * 100);
            
            return [
                'progress' => round($progress, 1),
                'stage' => 'processing',
                'elapsed_minutes' => round($elapsed, 1),
                'estimated_completion' => $start->addMinutes($estimatedTotal)->toISOString(),
            ];
        }

        return [
            'progress' => 5,
            'stage' => 'queued',
            'estimated_completion' => now()->addMinutes(($media->duration ?? 60) * 3)->toISOString(),
        ];
    }

    /**
     * Get compression ratio for video.
     */
    protected function getCompressionRatio(\App\Models\MediaAttachment $media): ?float
    {
        if (!isset($media->analysis_results['compression_ratio'])) {
            return null;
        }

        return (float) $media->analysis_results['compression_ratio'];
    }
} 