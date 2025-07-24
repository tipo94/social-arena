<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageService;
use App\Services\ImageProcessingService;
use App\Http\Requests\ImageUploadRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(
        protected StorageService $storageService,
        protected ImageProcessingService $imageProcessingService
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
} 