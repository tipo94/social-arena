<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ContentVisibilityService;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ContentVisibilityController extends Controller
{
    public function __construct(
        protected ContentVisibilityService $visibilityService
    ) {}

    /**
     * Get available visibility options for user.
     */
    public function getVisibilityOptions(Request $request): JsonResponse
    {
        $request->validate([
            'content_type' => 'sometimes|string|in:post,profile,activity',
        ]);

        $user = Auth::user();
        $contentType = $request->input('content_type', 'post');
        
        $options = $this->visibilityService->getAvailableVisibilityOptions($user, $contentType);
        $defaultVisibility = $this->visibilityService->getDefaultVisibilityForUser($user, $contentType);

        return response()->json([
            'success' => true,
            'data' => [
                'available_options' => $options,
                'default_visibility' => $defaultVisibility,
                'content_type' => $contentType,
                'constants' => [
                    'visibility_levels' => ContentVisibilityService::VISIBILITY_LEVELS,
                    'profile_levels' => ContentVisibilityService::PROFILE_VISIBILITY_LEVELS,
                    'interaction_levels' => ContentVisibilityService::INTERACTION_LEVELS,
                ],
            ],
        ]);
    }

    /**
     * Get visibility statistics for user.
     */
    public function getVisibilityStats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $stats = $this->visibilityService->getVisibilityStats($user);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Change visibility of a specific post.
     */
    public function changePostVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'visibility' => 'required|string|in:public,friends,close_friends,friends_of_friends,private,group,custom',
            'custom_audience' => 'sometimes|array|max:100',
            'custom_audience.*' => 'integer|exists:users,id',
            'allow_resharing' => 'sometimes|boolean',
            'allow_comments' => 'sometimes|boolean',
            'allow_reactions' => 'sometimes|boolean',
            'visibility_expires_at' => 'sometimes|date|after:now|before:' . now()->addMonths(3),
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            // Check if user can change visibility
            if (!$this->visibilityService->canChangeVisibility($user, $post, $request->input('visibility'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to change the visibility of this post, or the visibility option is not available.',
                ], 403);
            }

            DB::beginTransaction();

            // Update visibility using the enhanced method with history tracking
            $customAudience = $request->input('custom_audience', []);
            $post->updateVisibility($request->input('visibility'), $user, $customAudience);

            // Update interaction settings if provided
            $updateData = [];
            if ($request->has('allow_resharing')) {
                $updateData['allow_resharing'] = $request->boolean('allow_resharing');
            }
            if ($request->has('allow_comments')) {
                $updateData['allow_comments'] = $request->boolean('allow_comments');
            }
            if ($request->has('allow_reactions')) {
                $updateData['allow_reactions'] = $request->boolean('allow_reactions');
            }
            if ($request->has('visibility_expires_at')) {
                $updateData['visibility_expires_at'] = $request->input('visibility_expires_at');
            }

            if (!empty($updateData)) {
                $post->update($updateData);
            }

            DB::commit();

            $post->load(['user.profile']);

            return response()->json([
                'success' => true,
                'message' => 'Post visibility updated successfully',
                'data' => [
                    'post_id' => $post->id,
                    'visibility' => $post->visibility,
                    'custom_audience' => $post->custom_audience,
                    'allows_resharing' => $post->allow_resharing,
                    'allows_comments' => $post->allow_comments,
                    'allows_reactions' => $post->allow_reactions,
                    'visibility_expires_at' => $post->visibility_expires_at?->toISOString(),
                    'audience_summary' => $post->getAudienceSummary(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update post visibility',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Bulk change visibility for multiple posts.
     */
    public function bulkChangeVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => 'required|array|min:1|max:100',
            'post_ids.*' => 'integer|exists:posts,id',
            'visibility' => 'required|string|in:public,friends,close_friends,friends_of_friends,private,group',
            'allow_resharing' => 'sometimes|boolean',
            'allow_comments' => 'sometimes|boolean',
            'allow_reactions' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $postIds = $request->input('post_ids');
            
            // Get posts that belong to the user
            $posts = Post::whereIn('id', $postIds)
                        ->where('user_id', $user->id)
                        ->get();

            if ($posts->count() !== count($postIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some posts were not found or you do not have permission to modify them.',
                ], 403);
            }

            $results = $this->visibilityService->bulkUpdateVisibility(
                $posts,
                $request->input('visibility'),
                $user
            );

            // Update interaction settings if provided
            $updateData = [];
            if ($request->has('allow_resharing')) {
                $updateData['allow_resharing'] = $request->boolean('allow_resharing');
            }
            if ($request->has('allow_comments')) {
                $updateData['allow_comments'] = $request->boolean('allow_comments');
            }
            if ($request->has('allow_reactions')) {
                $updateData['allow_reactions'] = $request->boolean('allow_reactions');
            }

            if (!empty($updateData)) {
                Post::whereIn('id', $posts->pluck('id'))->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully updated {$results['updated']} posts",
                'data' => [
                    'updated_count' => $results['updated'],
                    'failed_count' => $results['failed'],
                    'errors' => $results['errors'],
                    'total_processed' => $posts->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk update post visibility',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get post audience information.
     */
    public function getPostAudience(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            // Check if user can view this information
            if ($post->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this post\'s audience information.',
                ], 403);
            }

            $audienceSummary = $post->getAudienceSummary();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'visibility' => $post->visibility,
                    'audience_summary' => $audienceSummary,
                    'interaction_settings' => [
                        'allows_resharing' => $post->allow_resharing,
                        'allows_comments' => $post->allow_comments,
                        'allows_reactions' => $post->allow_reactions,
                    ],
                    'visibility_metadata' => [
                        'is_temporary' => $post->isTemporary(),
                        'expires_at' => $post->visibility_expires_at?->toISOString(),
                        'has_expired' => $post->hasExpiredVisibility(),
                        'changed_at' => $post->visibility_changed_at?->toISOString(),
                        'history_count' => count($post->visibility_history ?? []),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get post audience information',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get visibility history for a post.
     */
    public function getVisibilityHistory(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            // Check if user can view this information
            if ($post->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to view this post\'s visibility history.',
                ], 403);
            }

            $history = $post->visibility_history ?? [];
            
            // Enrich history with user information
            $enrichedHistory = collect($history)->map(function ($entry) {
                if (isset($entry['changed_by'])) {
                    $user = User::find($entry['changed_by']);
                    $entry['changed_by_user'] = $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'username' => $user->username,
                    ] : null;
                }
                return $entry;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'current_visibility' => $post->visibility,
                    'history' => $enrichedHistory,
                    'total_changes' => count($history),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get visibility history',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if content is visible to current user.
     */
    public function checkContentVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'content_type' => 'required|string|in:post,profile,user_activity,user_content',
            'content_id' => 'required|integer',
            'target_user_id' => 'sometimes|integer|exists:users,id',
            'options' => 'sometimes|array',
        ]);

        try {
            $user = Auth::user();
            $contentType = $request->input('content_type');
            $contentId = $request->input('content_id');
            $targetUserId = $request->input('target_user_id');
            $options = $request->input('options', []);

            // Get the content based on type
            $content = match ($contentType) {
                'post' => Post::findOrFail($contentId),
                'profile', 'user_activity', 'user_content' => User::findOrFail($targetUserId ?? $contentId),
                default => throw new \InvalidArgumentException('Invalid content type'),
            };

            $isVisible = $this->visibilityService->isContentVisibleTo(
                $contentType,
                $content,
                $user,
                $options
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'content_type' => $contentType,
                    'content_id' => $contentId,
                    'is_visible' => $isVisible,
                    'viewer_id' => $user?->id,
                    'checked_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check content visibility',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update default visibility settings for user.
     */
    public function updateDefaultVisibility(Request $request): JsonResponse
    {
        $request->validate([
            'post_visibility_default' => 'sometimes|string|in:public,friends,close_friends,private',
            'reading_activity_visibility' => 'sometimes|string|in:public,friends,friends_of_friends,private',
            'who_can_see_posts' => 'sometimes|string|in:public,friends,close_friends,private',
        ]);

        try {
            $user = Auth::user();
            $updateData = [];

            if ($request->has('post_visibility_default')) {
                $updateData['post_visibility_default'] = $request->input('post_visibility_default');
            }
            if ($request->has('reading_activity_visibility')) {
                $updateData['reading_activity_visibility'] = $request->input('reading_activity_visibility');
            }
            if ($request->has('who_can_see_posts')) {
                $updateData['who_can_see_posts'] = $request->input('who_can_see_posts');
            }

            if (!empty($updateData)) {
                $user->profile->update($updateData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Default visibility settings updated successfully',
                'data' => [
                    'post_visibility_default' => $user->profile->post_visibility_default,
                    'reading_activity_visibility' => $user->profile->reading_activity_visibility,
                    'who_can_see_posts' => $user->profile->who_can_see_posts,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update default visibility settings',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
} 