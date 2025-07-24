<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PostEditingService;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\PostDeletionLog;
use App\Http\Requests\UpdatePostRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PostEditingController extends Controller
{
    public function __construct(
        protected PostEditingService $editingService
    ) {}

    /**
     * Check if a post can be edited.
     */
    public function checkEditPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            $editCheck = $this->editingService->canEditPost($post, $user);

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'can_edit' => $editCheck['can_edit'],
                    'reasons' => $editCheck['reasons'],
                    'restrictions' => [
                        'time_remaining' => $editCheck['time_remaining'] ?? null,
                        'edits_remaining' => $editCheck['edits_remaining'] ?? null,
                    ],
                    'edit_status' => $post->getEditStatus(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check edit permissions',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Edit a post with advanced tracking.
     */
    public function editPost(UpdatePostRequest $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'edit_reason' => 'sometimes|string|max:500',
            'send_notifications' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));
            
            $editData = $request->validated();
            unset($editData['post_id'], $editData['edit_reason'], $editData['send_notifications']);

            $options = [
                'reason' => $request->input('edit_reason'),
                'send_notifications' => $request->input('send_notifications', true),
                'source' => 'web',
            ];

            $result = $this->editingService->editPost($post, $editData, $user, $options);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'post' => $result['data']['post'],
                    'revision_info' => [
                        'version_number' => $result['data']['revision']->version_number,
                        'is_major_edit' => $result['data']['is_major_edit'],
                        'changes_summary' => $result['data']['changes_summary'],
                    ],
                    'edit_status' => $result['data']['post']->getEditStatus(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to edit post',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a post with tracking and recovery options.
     */
    public function deletePost(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'deletion_type' => 'sometimes|string|in:soft,permanent,admin',
            'reason' => 'sometimes|string|max:500',
            'notify_interactions' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            // Check permissions
            if (!$post->canEditBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this post',
                ], 403);
            }

            $options = [
                'deletion_type' => $request->input('deletion_type', 'soft'),
                'reason' => $request->input('reason'),
                'notify_interactions' => $request->input('notify_interactions', true),
                'can_be_restored' => $request->input('deletion_type', 'soft') === 'soft',
            ];

            $result = $this->editingService->deletePost($post, $user, $options);

            if (!$result['success']) {
                return response()->json($result, 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'deletion_log_id' => $result['data']['deletion_log']->id,
                    'can_be_restored' => $result['data']['can_be_restored'],
                    'restoration_deadline' => $result['data']['restoration_deadline']?->toISOString(),
                    'deletion_type' => $result['data']['deletion_log']->deletion_type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Restore a deleted post.
     */
    public function restorePost(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer',
            'restoration_reason' => 'sometimes|string|max:500',
        ]);

        try {
            $user = Auth::user();
            $postId = $request->input('post_id');

            $options = [
                'reason' => $request->input('restoration_reason'),
            ];

            $result = $this->editingService->restorePost($postId, $user, $options);

            if (!$result['success']) {
                return response()->json($result, $result['success'] ? 200 : 422);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => [
                    'post' => $result['data']['post'],
                    'deletion_log' => $result['data']['deletion_log'],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore post',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get edit history for a post.
     */
    public function getEditHistory(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'include_content' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            $history = $this->editingService->getEditHistory($post, $user);

            if ($history->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view edit history or no history exists',
                ], 403);
            }

            $includeContent = $request->input('include_content', false);

            $formattedHistory = $history->map(function ($revision) use ($includeContent) {
                $data = [
                    'id' => $revision->id,
                    'version_number' => $revision->version_number,
                    'edited_by' => [
                        'id' => $revision->user->id,
                        'name' => $revision->user->name,
                        'username' => $revision->user->username,
                    ],
                    'edited_at' => $revision->created_at->toISOString(),
                    'edit_reason' => $revision->edit_reason,
                    'edit_source' => $revision->edit_source,
                    'is_major_edit' => $revision->is_major_edit,
                    'changes_summary' => $revision->getChangeSummary(),
                    'impact_level' => $revision->getEditImpactLevel(),
                    'content_changes' => [
                        'characters_added' => $revision->characters_added,
                        'characters_removed' => $revision->characters_removed,
                        'content_length' => $revision->content_length,
                    ],
                ];

                if ($includeContent) {
                    $data['content'] = $revision->content;
                    $data['metadata'] = $revision->metadata;
                    $data['visibility'] = $revision->visibility;
                }

                return $data;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $post->id,
                    'current_version' => $post->current_version,
                    'total_revisions' => $history->count(),
                    'revisions' => $formattedHistory,
                    'edit_summary' => $post->getEditSummary(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get edit history',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get diff between two revisions.
     */
    public function getRevisionDiff(Request $request): JsonResponse
    {
        $request->validate([
            'from_revision_id' => 'required|integer|exists:post_revisions,id',
            'to_revision_id' => 'required|integer|exists:post_revisions,id',
        ]);

        try {
            $user = Auth::user();
            $fromRevision = PostRevision::findOrFail($request->input('from_revision_id'));
            $toRevision = PostRevision::findOrFail($request->input('to_revision_id'));

            // Check if user can access these revisions
            if ($fromRevision->post->user_id !== $user->id && !$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to view these revisions',
                ], 403);
            }

            if ($fromRevision->post_id !== $toRevision->post_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Revisions must belong to the same post',
                ], 422);
            }

            $diff = $this->editingService->getRevisionDiff($fromRevision, $toRevision);

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $fromRevision->post_id,
                    'from_revision' => [
                        'id' => $fromRevision->id,
                        'version_number' => $fromRevision->version_number,
                        'created_at' => $fromRevision->created_at->toISOString(),
                    ],
                    'to_revision' => [
                        'id' => $toRevision->id,
                        'version_number' => $toRevision->version_number,
                        'created_at' => $toRevision->created_at->toISOString(),
                    ],
                    'diff' => $diff,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get revision diff',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Lock a post for editing.
     */
    public function lockPost(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
            'reason' => 'sometimes|string|max:500',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            $locked = $this->editingService->lockPostForEditing($post, $user, $request->input('reason'));

            if (!$locked) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to lock this post',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Post locked for editing',
                'data' => [
                    'post_id' => $post->id,
                    'locked_at' => $post->fresh()->editing_locked_at->toISOString(),
                    'edit_status' => $post->fresh()->getEditStatus(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to lock post',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unlock a post for editing.
     */
    public function unlockPost(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer|exists:posts,id',
        ]);

        try {
            $user = Auth::user();
            $post = Post::findOrFail($request->input('post_id'));

            $unlocked = $this->editingService->unlockPostForEditing($post, $user);

            if (!$unlocked) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to unlock this post',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'message' => 'Post unlocked for editing',
                'data' => [
                    'post_id' => $post->id,
                    'edit_status' => $post->fresh()->getEditStatus(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unlock post',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get user's deleted posts that can be restored.
     */
    public function getRestorablePosts(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $deletionLogs = PostDeletionLog::with(['postUser', 'deletedBy'])
                                         ->where('post_user_id', $user->id)
                                         ->restorable()
                                         ->orderBy('deleted_at', 'desc')
                                         ->paginate(15);

            $formattedLogs = $deletionLogs->getCollection()->map(function ($log) {
                return [
                    'deletion_log_id' => $log->id,
                    'post_id' => $log->post_id,
                    'post_summary' => $log->getDeletionSummary(),
                    'deleted_at' => $log->deleted_at->toISOString(),
                    'deleted_by' => [
                        'id' => $log->deletedBy->id,
                        'name' => $log->deletedBy->name,
                        'username' => $log->deletedBy->username,
                    ],
                    'deletion_reason' => $log->deletion_reason,
                    'restoration_deadline' => $log->restoration_deadline?->toISOString(),
                    'time_remaining' => $log->getRestorationTimeRemaining(),
                    'can_be_restored' => $log->canBeRestored(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'deletions' => $formattedLogs,
                    'pagination' => [
                        'current_page' => $deletionLogs->currentPage(),
                        'last_page' => $deletionLogs->lastPage(),
                        'per_page' => $deletionLogs->perPage(),
                        'total' => $deletionLogs->total(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get restorable posts',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get deletion logs for a specific post (admin only).
     */
    public function getDeletionLogs(Request $request): JsonResponse
    {
        $request->validate([
            'post_id' => 'required|integer',
        ]);

        try {
            $user = Auth::user();
            
            if (!$user->isAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin access required',
                ], 403);
            }

            $deletionLogs = PostDeletionLog::with(['postUser', 'deletedBy', 'restoredBy'])
                                         ->where('post_id', $request->input('post_id'))
                                         ->orderBy('deleted_at', 'desc')
                                         ->get();

            $formattedLogs = $deletionLogs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'post_id' => $log->post_id,
                    'deletion_details' => [
                        'type' => $log->deletion_type,
                        'reason' => $log->deletion_reason,
                        'deleted_at' => $log->deleted_at->toISOString(),
                        'is_admin_action' => $log->is_admin_action,
                    ],
                    'users' => [
                        'post_owner' => [
                            'id' => $log->postUser->id,
                            'name' => $log->postUser->name,
                            'username' => $log->postUser->username,
                        ],
                        'deleted_by' => [
                            'id' => $log->deletedBy->id,
                            'name' => $log->deletedBy->name,
                            'username' => $log->deletedBy->username,
                        ],
                        'restored_by' => $log->restoredBy ? [
                            'id' => $log->restoredBy->id,
                            'name' => $log->restoredBy->name,
                            'username' => $log->restoredBy->username,
                        ] : null,
                    ],
                    'restoration' => [
                        'was_restored' => $log->was_restored,
                        'restored_at' => $log->restored_at?->toISOString(),
                        'restoration_reason' => $log->restoration_reason,
                        'restoration_deadline' => $log->restoration_deadline?->toISOString(),
                    ],
                    'post_snapshot' => $log->getDeletionSummary(),
                    'moderator_notes' => $log->getFormattedModeratorNotes(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'post_id' => $request->input('post_id'),
                    'deletion_logs' => $formattedLogs,
                    'total_deletions' => $deletionLogs->count(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get deletion logs',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
} 