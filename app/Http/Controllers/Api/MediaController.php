<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MediaController extends Controller
{
    public function __construct(
        protected StorageService $storageService
    ) {}

    /**
     * Upload an image
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
} 