<?php

namespace App\Services;

use App\Models\Post;
use App\Models\User;
use App\Models\PostRevision;
use App\Models\PostDeletionLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PostEditingService
{
    /**
     * Edit time limits by post type (in hours).
     */
    const EDIT_TIME_LIMITS = [
        'text' => 24,           // 24 hours for text posts
        'image' => 6,           // 6 hours for image posts
        'video' => 2,           // 2 hours for video posts
        'link' => 12,           // 12 hours for link posts
        'book_review' => 48,    // 48 hours for book reviews
        'poll' => 1,            // 1 hour for polls (after votes start)
    ];

    /**
     * Major edit thresholds (percentage of content changed).
     */
    const MAJOR_EDIT_THRESHOLD = 30; // 30% change is considered major

    /**
     * Maximum number of edits allowed per post.
     */
    const MAX_EDITS_PER_POST = 10;

    /**
     * Soft delete retention period (days).
     */
    const SOFT_DELETE_RETENTION_DAYS = 30;

    /**
     * Check if a post can be edited by a user.
     */
    public function canEditPost(Post $post, User $user, array $options = []): array
    {
        $canEdit = false;
        $reasons = [];

        // Basic ownership check
        if (!$post->canEditBy($user)) {
            $reasons[] = 'You do not have permission to edit this post';
            return ['can_edit' => false, 'reasons' => $reasons];
        }

        // Check if editing is globally disabled for this post
        if (!$post->allow_editing) {
            $reasons[] = 'Editing has been disabled for this post';
            return ['can_edit' => false, 'reasons' => $reasons];
        }

        // Check if post is locked
        if ($post->editing_locked_at) {
            $reasons[] = 'This post is currently locked for editing';
            return ['can_edit' => false, 'reasons' => $reasons];
        }

        // Check edit deadline
        if ($post->edit_deadline && now() > $post->edit_deadline) {
            $reasons[] = 'The editing deadline for this post has passed';
            return ['can_edit' => false, 'reasons' => $reasons];
        }

        // Check time-based restrictions (not for admins)
        if (!$user->isAdmin()) {
            $timeLimit = $this->getEditTimeLimit($post);
            if ($timeLimit && now() > $post->created_at->addHours($timeLimit)) {
                $reasons[] = "Editing is only allowed within {$timeLimit} hours of posting";
                return ['can_edit' => false, 'reasons' => $reasons];
            }
        }

        // Check edit count limit
        if ($post->edit_count >= self::MAX_EDITS_PER_POST && !$user->isAdmin()) {
            $reasons[] = 'Maximum number of edits reached for this post';
            return ['can_edit' => false, 'reasons' => $reasons];
        }

        // Check if post has interactions that might restrict editing
        if (!$options['ignore_interactions'] ?? false) {
            $restrictionReasons = $this->checkInteractionRestrictions($post, $user);
            if (!empty($restrictionReasons)) {
                $reasons = array_merge($reasons, $restrictionReasons);
                return ['can_edit' => false, 'reasons' => $reasons];
            }
        }

        return [
            'can_edit' => true,
            'reasons' => [],
            'time_remaining' => $this->getEditTimeRemaining($post),
            'edits_remaining' => self::MAX_EDITS_PER_POST - $post->edit_count,
        ];
    }

    /**
     * Edit a post with full revision tracking.
     */
    public function editPost(Post $post, array $data, User $user, array $options = []): array
    {
        // Check edit permissions
        $editCheck = $this->canEditPost($post, $user, $options);
        if (!$editCheck['can_edit']) {
            return [
                'success' => false,
                'message' => 'Cannot edit post',
                'errors' => $editCheck['reasons'],
            ];
        }

        try {
            DB::beginTransaction();

            // Create revision before making changes
            $revision = $this->createRevision($post, $data, $user, $options);

            // Store original content if this is the first edit
            if ($post->edit_count === 0) {
                $post->original_content = [
                    'content' => $post->content,
                    'type' => $post->type,
                    'metadata' => $post->metadata,
                    'visibility' => $post->visibility,
                    'media_attachments' => $post->mediaAttachments()->select('id', 'filename', 'type')->get()->toArray(),
                ];
            }

            // Calculate changes and update post
            $changes = $this->calculateChanges($post, $data);
            $post->update($data);

            // Update edit tracking fields
            $this->updateEditTracking($post, $user, $changes, $revision);

            // Handle media changes
            if (isset($data['media_ids'])) {
                $this->handleMediaChanges($post, $data['media_ids'], $revision);
            }

            // Set edit deadline if not already set
            if (!$post->edit_deadline && !$user->isAdmin()) {
                $timeLimit = $this->getEditTimeLimit($post);
                if ($timeLimit) {
                    $post->edit_deadline = $post->created_at->addHours($timeLimit);
                    $post->save();
                }
            }

            // Send notifications if this is a major edit
            if ($revision->is_major_edit && ($options['send_notifications'] ?? true)) {
                $this->scheduleEditNotifications($post, $revision);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => [
                    'post' => $post->fresh(),
                    'revision' => $revision,
                    'is_major_edit' => $revision->is_major_edit,
                    'changes_summary' => $this->getChangesSummary($changes),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to edit post',
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Soft delete a post with recovery options.
     */
    public function deletePost(Post $post, User $user, array $options = []): array
    {
        try {
            DB::beginTransaction();

            // Create deletion log
            $deletionLog = PostDeletionLog::create([
                'post_id' => $post->id,
                'post_user_id' => $post->user_id,
                'deleted_by' => $user->id,
                'deletion_type' => $options['deletion_type'] ?? 'soft',
                'deletion_reason' => $options['reason'] ?? null,
                'post_snapshot' => $this->createPostSnapshot($post),
                'original_created_at' => $post->created_at,
                'deleted_at' => now(),
                'restoration_deadline' => now()->addDays(self::SOFT_DELETE_RETENTION_DAYS),
                'deletion_ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'is_admin_action' => $user->isAdmin() && $user->id !== $post->user_id,
            ]);

            // Update post with deletion information
            $post->update([
                'deletion_reason' => $options['reason'] ?? null,
                'deleted_by' => $user->id,
                'deletion_scheduled_at' => now(),
                'permanent_deletion_at' => now()->addDays(self::SOFT_DELETE_RETENTION_DAYS),
                'can_be_restored' => $options['can_be_restored'] ?? true,
            ]);

            // Perform soft delete
            $post->delete();

            // Schedule notifications for post interactions
            if ($options['notify_interactions'] ?? true) {
                $this->scheduleDeletionNotifications($post, $deletionLog);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Post deleted successfully',
                'data' => [
                    'deletion_log' => $deletionLog,
                    'can_be_restored' => $post->can_be_restored,
                    'restoration_deadline' => $post->permanent_deletion_at,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to delete post',
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Restore a soft-deleted post.
     */
    public function restorePost(int $postId, User $user, array $options = []): array
    {
        try {
            DB::beginTransaction();

            $post = Post::withTrashed()->findOrFail($postId);

            // Check if restoration is allowed
            if (!$post->can_be_restored) {
                return [
                    'success' => false,
                    'message' => 'This post cannot be restored',
                ];
            }

            // Check restoration deadline
            if ($post->permanent_deletion_at && now() > $post->permanent_deletion_at) {
                return [
                    'success' => false,
                    'message' => 'Restoration deadline has passed',
                ];
            }

            // Check permissions
            if ($post->user_id !== $user->id && !$user->isAdmin()) {
                return [
                    'success' => false,
                    'message' => 'You do not have permission to restore this post',
                ];
            }

            // Restore the post
            $post->restore();

            // Clear deletion fields
            $post->update([
                'deletion_reason' => null,
                'deleted_by' => null,
                'deletion_scheduled_at' => null,
                'permanent_deletion_at' => null,
                'can_be_restored' => true,
            ]);

            // Update deletion log
            $deletionLog = PostDeletionLog::where('post_id', $postId)
                                         ->where('was_restored', false)
                                         ->latest()
                                         ->first();

            if ($deletionLog) {
                $deletionLog->update([
                    'was_restored' => true,
                    'restored_at' => now(),
                    'restored_by' => $user->id,
                    'restoration_reason' => $options['reason'] ?? null,
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Post restored successfully',
                'data' => [
                    'post' => $post->fresh(),
                    'deletion_log' => $deletionLog,
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to restore post',
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Get edit history for a post.
     */
    public function getEditHistory(Post $post, User $user): Collection
    {
        // Check if user can view edit history
        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            return collect();
        }

        return PostRevision::where('post_id', $post->id)
                          ->with('user:id,name,username')
                          ->orderBy('version_number', 'desc')
                          ->get();
    }

    /**
     * Get diff between two revisions.
     */
    public function getRevisionDiff(PostRevision $fromRevision, PostRevision $toRevision): array
    {
        return [
            'content_diff' => $this->calculateContentDiff($fromRevision->content, $toRevision->content),
            'metadata_diff' => $this->calculateArrayDiff($fromRevision->metadata, $toRevision->metadata),
            'visibility_changed' => $fromRevision->visibility !== $toRevision->visibility,
            'type_changed' => $fromRevision->type !== $toRevision->type,
            'media_diff' => $this->calculateArrayDiff($fromRevision->media_attachments, $toRevision->media_attachments),
        ];
    }

    /**
     * Lock a post for editing.
     */
    public function lockPostForEditing(Post $post, User $user, string $reason = null): bool
    {
        if (!$user->isAdmin() && $post->user_id !== $user->id) {
            return false;
        }

        $post->update([
            'editing_locked_at' => now(),
            'allow_editing' => false,
        ]);

        return true;
    }

    /**
     * Unlock a post for editing.
     */
    public function unlockPostForEditing(Post $post, User $user): bool
    {
        if (!$user->isAdmin() && $post->user_id !== $user->id) {
            return false;
        }

        $post->update([
            'editing_locked_at' => null,
            'allow_editing' => true,
        ]);

        return true;
    }

    /**
     * Get posts scheduled for permanent deletion.
     */
    public function getPostsScheduledForDeletion(): Collection
    {
        return Post::onlyTrashed()
                  ->whereNotNull('permanent_deletion_at')
                  ->where('permanent_deletion_at', '<=', now())
                  ->where('can_be_restored', true)
                  ->get();
    }

    /**
     * Permanently delete posts that have passed their retention period.
     */
    public function processPermanentDeletions(): array
    {
        $posts = $this->getPostsScheduledForDeletion();
        $deletedCount = 0;
        $errors = [];

        foreach ($posts as $post) {
            try {
                DB::beginTransaction();

                // Update deletion log
                PostDeletionLog::where('post_id', $post->id)
                              ->where('was_restored', false)
                              ->update(['was_restored' => false]); // Mark as permanently deleted

                // Force delete the post
                $post->forceDelete();

                $deletedCount++;
                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $errors[] = "Failed to delete post {$post->id}: " . $e->getMessage();
            }
        }

        return [
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Create a revision record.
     */
    protected function createRevision(Post $post, array $newData, User $user, array $options): PostRevision
    {
        $changes = $this->calculateChanges($post, $newData);
        $diff = $this->calculateDetailedDiff($post, $newData);
        
        return PostRevision::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'version_number' => $post->current_version + 1,
            'content' => $post->content,
            'type' => $post->type,
            'metadata' => $post->metadata,
            'visibility' => $post->visibility,
            'media_attachments' => $post->mediaAttachments()->select('id', 'filename', 'type')->get()->toArray(),
            'changes_made' => $changes,
            'diff_data' => $diff,
            'edit_reason' => $options['reason'] ?? null,
            'edit_source' => $options['source'] ?? 'web',
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
            'is_major_edit' => $this->isMajorEdit($post, $newData),
            'content_length' => strlen($post->content ?? ''),
            'characters_added' => $diff['characters_added'] ?? 0,
            'characters_removed' => $diff['characters_removed'] ?? 0,
        ]);
    }

    /**
     * Calculate what changed between versions.
     */
    protected function calculateChanges(Post $post, array $newData): array
    {
        $changes = [];

        foreach ($newData as $field => $newValue) {
            $oldValue = $post->getAttribute($field);
            
            if ($oldValue !== $newValue) {
                $changes[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }

    /**
     * Calculate detailed diff information.
     */
    protected function calculateDetailedDiff(Post $post, array $newData): array
    {
        $diff = [];

        // Content diff
        if (isset($newData['content'])) {
            $oldContent = $post->content ?? '';
            $newContent = $newData['content'];
            
            $diff['content_diff'] = $this->calculateContentDiff($oldContent, $newContent);
            $diff['characters_added'] = max(0, strlen($newContent) - strlen($oldContent));
            $diff['characters_removed'] = max(0, strlen($oldContent) - strlen($newContent));
        }

        return $diff;
    }

    /**
     * Calculate content diff using simple algorithm.
     */
    protected function calculateContentDiff(string $oldContent, string $newContent): array
    {
        // Simple word-based diff
        $oldWords = explode(' ', $oldContent);
        $newWords = explode(' ', $newContent);
        
        $added = array_diff($newWords, $oldWords);
        $removed = array_diff($oldWords, $newWords);
        
        return [
            'added_words' => array_values($added),
            'removed_words' => array_values($removed),
            'similarity_percentage' => $this->calculateSimilarity($oldContent, $newContent),
        ];
    }

    /**
     * Calculate similarity percentage between two strings.
     */
    protected function calculateSimilarity(string $str1, string $str2): float
    {
        similar_text($str1, $str2, $percent);
        return round($percent, 2);
    }

    /**
     * Check if this is a major edit.
     */
    protected function isMajorEdit(Post $post, array $newData): bool
    {
        if (!isset($newData['content'])) {
            return false;
        }

        $similarity = $this->calculateSimilarity($post->content ?? '', $newData['content']);
        return (100 - $similarity) >= self::MAJOR_EDIT_THRESHOLD;
    }

    /**
     * Update post edit tracking fields.
     */
    protected function updateEditTracking(Post $post, User $user, array $changes, PostRevision $revision): void
    {
        $editHistory = $post->edit_history ?? [];
        $editHistory[] = [
            'edited_at' => now()->toISOString(),
            'edited_by' => $user->id,
            'version' => $revision->version_number,
            'changes_summary' => array_keys($changes),
            'is_major_edit' => $revision->is_major_edit,
        ];

        $post->update([
            'edit_history' => $editHistory,
            'last_edited_at' => now(),
            'last_edited_by' => $user->id,
            'edit_count' => $post->edit_count + 1,
            'is_edited' => true,
            'current_version' => $revision->version_number,
        ]);
    }

    /**
     * Handle media changes during edit.
     */
    protected function handleMediaChanges(Post $post, array $newMediaIds, PostRevision $revision): void
    {
        $oldMediaIds = $post->mediaAttachments()->pluck('id')->toArray();
        
        if ($oldMediaIds !== $newMediaIds) {
            // Store media change in revision
            $revision->update([
                'media_attachments' => [
                    'old' => $oldMediaIds,
                    'new' => $newMediaIds,
                ],
            ]);

            // Update media attachments
            $post->mediaAttachments()->update(['attachable_id' => null]);
            
            $mediaAttachments = \App\Models\MediaAttachment::whereIn('id', $newMediaIds)
                                                          ->where('user_id', $post->user_id)
                                                          ->get();

            foreach ($mediaAttachments as $media) {
                $media->update([
                    'attachable_type' => Post::class,
                    'attachable_id' => $post->id,
                ]);
            }
        }
    }

    /**
     * Get edit time limit for post type.
     */
    protected function getEditTimeLimit(Post $post): ?int
    {
        return self::EDIT_TIME_LIMITS[$post->type] ?? self::EDIT_TIME_LIMITS['text'];
    }

    /**
     * Get remaining edit time.
     */
    protected function getEditTimeRemaining(Post $post): ?array
    {
        $timeLimit = $this->getEditTimeLimit($post);
        if (!$timeLimit) {
            return null;
        }

        $deadline = $post->edit_deadline ?? $post->created_at->addHours($timeLimit);
        $remaining = now()->diffInMinutes($deadline, false);

        if ($remaining <= 0) {
            return ['expired' => true];
        }

        return [
            'expired' => false,
            'minutes_remaining' => $remaining,
            'deadline' => $deadline->toISOString(),
        ];
    }

    /**
     * Check interaction-based edit restrictions.
     */
    protected function checkInteractionRestrictions(Post $post, User $user): array
    {
        $reasons = [];

        // If post has significant interactions, be more restrictive
        if ($post->likes_count > 10 || $post->comments_count > 5) {
            $reasons[] = 'Post has significant interactions - editing may be restricted';
        }

        return $reasons;
    }

    /**
     * Create complete post snapshot for deletion log.
     */
    protected function createPostSnapshot(Post $post): array
    {
        return [
            'id' => $post->id,
            'content' => $post->content,
            'type' => $post->type,
            'metadata' => $post->metadata,
            'visibility' => $post->visibility,
            'likes_count' => $post->likes_count,
            'comments_count' => $post->comments_count,
            'shares_count' => $post->shares_count,
            'media_attachments' => $post->mediaAttachments()->get()->toArray(),
            'created_at' => $post->created_at->toISOString(),
            'edit_history' => $post->edit_history,
        ];
    }

    /**
     * Calculate array differences.
     */
    protected function calculateArrayDiff(?array $old, ?array $new): array
    {
        $old = $old ?? [];
        $new = $new ?? [];
        
        return [
            'added' => array_diff_assoc($new, $old),
            'removed' => array_diff_assoc($old, $new),
            'unchanged' => array_intersect_assoc($old, $new),
        ];
    }

    /**
     * Get human-readable changes summary.
     */
    protected function getChangesSummary(array $changes): string
    {
        $summaries = [];
        
        foreach (array_keys($changes) as $field) {
            $summaries[] = match ($field) {
                'content' => 'Content modified',
                'visibility' => 'Visibility changed',
                'type' => 'Post type changed',
                'metadata' => 'Additional information updated',
                default => ucfirst($field) . ' updated',
            };
        }

        return implode(', ', $summaries);
    }

    /**
     * Schedule edit notifications (placeholder for notification system).
     */
    protected function scheduleEditNotifications(Post $post, PostRevision $revision): void
    {
        // This would integrate with a notification service
        // For now, just mark that notifications should be sent
        $post->update(['edit_notifications_sent' => false]);
    }

    /**
     * Schedule deletion notifications (placeholder for notification system).
     */
    protected function scheduleDeletionNotifications(Post $post, PostDeletionLog $deletionLog): void
    {
        // This would notify users who interacted with the post
        // For now, just log the action
    }
} 