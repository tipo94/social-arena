<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\ProcessAccountDeletionJob;
use App\Jobs\SendAccountDeletionNotificationJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AccountDeletionService
{
    /**
     * Grace period for account recovery (in days)
     */
    const GRACE_PERIOD_DAYS = 30;

    /**
     * Request account deletion with grace period
     */
    public function requestDeletion(User $user, string $reason = null, string $password = null): array
    {
        try {
            // Verify password if provided
            if ($password && !password_verify($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Invalid password provided',
                    'errors' => ['password' => ['The provided password is incorrect.']],
                ];
            }

            // Check if deletion is already requested
            if ($user->deletion_requested_at) {
                $daysSinceRequest = now()->diffInDays($user->deletion_requested_at);
                $remainingDays = self::GRACE_PERIOD_DAYS - $daysSinceRequest;

                return [
                    'success' => false,
                    'message' => 'Account deletion already requested',
                    'data' => [
                        'deletion_requested_at' => $user->deletion_requested_at,
                        'days_remaining' => max(0, $remainingDays),
                        'will_be_deleted_at' => $user->deletion_requested_at->addDays(self::GRACE_PERIOD_DAYS),
                    ],
                ];
            }

            // Set deletion request timestamp
            $deletionDate = now()->addDays(self::GRACE_PERIOD_DAYS);
            
            $user->update([
                'deletion_requested_at' => now(),
                'deletion_reason' => $reason,
                'will_be_deleted_at' => $deletionDate,
                'is_active' => false, // Deactivate account immediately
            ]);

            // Log the deletion request
            Log::info('Account deletion requested', [
                'user_id' => $user->id,
                'email' => $user->email,
                'reason' => $reason,
                'deletion_date' => $deletionDate,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            // Queue notification email
            SendAccountDeletionNotificationJob::dispatch($user, 'requested');

            // Schedule the actual deletion job
            ProcessAccountDeletionJob::dispatch($user)->delay($deletionDate);

            return [
                'success' => true,
                'message' => 'Account deletion has been scheduled',
                'data' => [
                    'deletion_requested_at' => $user->deletion_requested_at,
                    'will_be_deleted_at' => $deletionDate,
                    'grace_period_days' => self::GRACE_PERIOD_DAYS,
                    'can_cancel_until' => $deletionDate->subDay(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to request account deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to request account deletion',
                'errors' => ['system' => ['An error occurred while processing your request.']],
            ];
        }
    }

    /**
     * Cancel account deletion request
     */
    public function cancelDeletion(User $user): array
    {
        try {
            if (!$user->deletion_requested_at) {
                return [
                    'success' => false,
                    'message' => 'No deletion request found',
                ];
            }

            // Check if still within grace period
            $daysSinceRequest = now()->diffInDays($user->deletion_requested_at);
            if ($daysSinceRequest >= self::GRACE_PERIOD_DAYS) {
                return [
                    'success' => false,
                    'message' => 'Grace period has expired, account deletion cannot be cancelled',
                ];
            }

            // Cancel deletion
            $user->update([
                'deletion_requested_at' => null,
                'deletion_reason' => null,
                'will_be_deleted_at' => null,
                'is_active' => true, // Reactivate account
            ]);

            // Log the cancellation
            Log::info('Account deletion cancelled', [
                'user_id' => $user->id,
                'email' => $user->email,
                'days_since_request' => $daysSinceRequest,
                'ip_address' => request()->ip(),
            ]);

            // Send cancellation notification
            SendAccountDeletionNotificationJob::dispatch($user, 'cancelled');

            return [
                'success' => true,
                'message' => 'Account deletion has been cancelled successfully',
                'data' => [
                    'reactivated_at' => now(),
                ],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to cancel account deletion', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to cancel account deletion',
                'errors' => ['system' => ['An error occurred while processing your request.']],
            ];
        }
    }

    /**
     * Get account deletion status
     */
    public function getDeletionStatus(User $user): array
    {
        if (!$user->deletion_requested_at) {
            return [
                'deletion_requested' => false,
                'is_active' => $user->is_active,
            ];
        }

        $daysSinceRequest = now()->diffInDays($user->deletion_requested_at);
        $remainingDays = max(0, self::GRACE_PERIOD_DAYS - $daysSinceRequest);

        return [
            'deletion_requested' => true,
            'deletion_requested_at' => $user->deletion_requested_at,
            'deletion_reason' => $user->deletion_reason,
            'will_be_deleted_at' => $user->will_be_deleted_at,
            'days_remaining' => $remainingDays,
            'can_cancel' => $remainingDays > 0,
            'is_active' => $user->is_active,
        ];
    }

    /**
     * Permanently delete user account and all associated data
     */
    public function permanentlyDeleteAccount(User $user): array
    {
        try {
            DB::beginTransaction();

            $deletionSummary = [
                'user_id' => $user->id,
                'email' => $user->email,
                'deleted_at' => now(),
                'deleted_data' => [],
            ];

            // 1. Delete user's posts and associated data
            $postsCount = $user->posts()->count();
            if ($postsCount > 0) {
                // Delete post comments
                $user->posts()->with('comments')->get()->each(function ($post) {
                    $post->comments()->delete();
                });
                
                // Delete post likes
                $user->posts()->with('likes')->get()->each(function ($post) {
                    $post->likes()->delete();
                });
                
                // Delete posts
                $user->posts()->delete();
                $deletionSummary['deleted_data']['posts'] = $postsCount;
            }

            // 2. Delete user's comments on other posts
            $commentsCount = $user->comments()->count();
            if ($commentsCount > 0) {
                $user->comments()->delete();
                $deletionSummary['deleted_data']['comments'] = $commentsCount;
            }

            // 3. Delete user's likes
            $likesCount = $user->likes()->count();
            if ($likesCount > 0) {
                $user->likes()->delete();
                $deletionSummary['deleted_data']['likes'] = $likesCount;
            }

            // 4. Handle friendships
            $friendshipsCount = $user->sentFriendRequests()->count() + $user->receivedFriendRequests()->count();
            if ($friendshipsCount > 0) {
                // Remove from both sent and received friendships
                $user->sentFriendRequests()->delete();
                $user->receivedFriendRequests()->delete();
                $deletionSummary['deleted_data']['friendships'] = $friendshipsCount;
            }

            // 5. Delete messages (if messaging system exists)
            if (method_exists($user, 'messages')) {
                $messagesCount = $user->messages()->count();
                if ($messagesCount > 0) {
                    $user->messages()->delete();
                    $deletionSummary['deleted_data']['messages'] = $messagesCount;
                }
            }

            // 6. Delete notifications
            if (method_exists($user, 'notifications')) {
                $notificationsCount = $user->notifications()->count();
                if ($notificationsCount > 0) {
                    $user->notifications()->delete();
                    $deletionSummary['deleted_data']['notifications'] = $notificationsCount;
                }
            }

            // 7. Delete user files (avatar, cover images, etc.)
            $this->deleteUserFiles($user);
            $deletionSummary['deleted_data']['files'] = 'avatar, cover_image, uploads';

            // 8. Delete user profile
            if ($user->profile) {
                $user->profile->delete();
                $deletionSummary['deleted_data']['profile'] = true;
            }

            // 9. Revoke all API tokens
            $tokensCount = $user->tokens()->count();
            if ($tokensCount > 0) {
                $user->tokens()->delete();
                $deletionSummary['deleted_data']['api_tokens'] = $tokensCount;
            }

            // 10. Finally delete the user record
            $user->forceDelete(); // Hard delete

            DB::commit();

            // Log successful deletion
            Log::info('Account permanently deleted', $deletionSummary);

            // Send final notification email (to a backup address if provided)
            // This is for GDPR compliance confirmation
            if (config('app.gdpr_notification_email')) {
                // Send anonymized confirmation to admin
            }

            return [
                'success' => true,
                'message' => 'Account has been permanently deleted',
                'data' => [
                    'deleted_at' => $deletionSummary['deleted_at'],
                    'deletion_summary' => $deletionSummary['deleted_data'],
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to permanently delete account', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to delete account',
                'errors' => ['system' => ['An error occurred during account deletion.']],
            ];
        }
    }

    /**
     * Delete all user files (avatar, cover images, uploads)
     */
    protected function deleteUserFiles(User $user): void
    {
        try {
            // Delete avatar
            if ($user->profile && $user->profile->avatar_url) {
                Storage::disk('public')->delete($user->profile->avatar_url);
            }

            // Delete cover image
            if ($user->profile && $user->profile->cover_image_url) {
                Storage::disk('public')->delete($user->profile->cover_image_url);
            }

            // Delete user's uploaded files (if there's a user uploads directory)
            $userUploadsPath = "uploads/users/{$user->id}";
            if (Storage::disk('public')->exists($userUploadsPath)) {
                Storage::disk('public')->deleteDirectory($userUploadsPath);
            }

            // Also check private storage
            if (Storage::disk('private')->exists($userUploadsPath)) {
                Storage::disk('private')->deleteDirectory($userUploadsPath);
            }

        } catch (\Exception $e) {
            Log::warning('Failed to delete some user files', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Export user data for GDPR compliance
     */
    public function exportUserData(User $user): array
    {
        try {
            $exportData = [
                'export_generated_at' => now()->toISOString(),
                'user_information' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'username' => $user->username,
                    'created_at' => $user->created_at,
                    'last_login_at' => $user->last_login_at,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'profile_information' => $user->profile ? [
                    'bio' => $user->profile->bio,
                    'location' => $user->profile->location,
                    'website' => $user->profile->website,
                    'birth_date' => $user->profile->birth_date,
                    'gender' => $user->profile->gender,
                    'occupation' => $user->profile->occupation,
                    'education' => $user->profile->education,
                    'reading_preferences' => $user->profile->getReadingPreferences(),
                    'privacy_settings' => $user->profile->getPrivacySettings(),
                    'notification_settings' => $user->profile->getNotificationSettings(),
                ] : null,
                'posts' => $user->posts()->with('comments', 'likes')->get()->map(function ($post) {
                    return [
                        'id' => $post->id,
                        'content' => $post->content,
                        'created_at' => $post->created_at,
                        'likes_count' => $post->likes_count,
                        'comments_count' => $post->comments_count,
                    ];
                }),
                'comments' => $user->comments()->get()->map(function ($comment) {
                    return [
                        'id' => $comment->id,
                        'content' => $comment->content,
                        'post_id' => $comment->post_id,
                        'created_at' => $comment->created_at,
                    ];
                }),
                'friendships' => [
                    'friends' => $user->friends()->map(function ($friend) {
                        return [
                            'id' => $friend->id,
                            'name' => $friend->name,
                            'username' => $friend->username,
                        ];
                    }),
                    'sent_requests' => $user->sentFriendRequests()->get()->map(function ($request) {
                        return [
                            'friend_id' => $request->friend_id,
                            'status' => $request->status,
                            'requested_at' => $request->requested_at,
                        ];
                    }),
                    'received_requests' => $user->receivedFriendRequests()->get()->map(function ($request) {
                        return [
                            'user_id' => $request->user_id,
                            'status' => $request->status,
                            'requested_at' => $request->requested_at,
                        ];
                    }),
                ],
            ];

            return [
                'success' => true,
                'data' => $exportData,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to export user data', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to export user data',
                'errors' => ['system' => ['An error occurred while exporting your data.']],
            ];
        }
    }

    /**
     * Get users scheduled for deletion (for admin/cleanup jobs)
     */
    public function getUsersScheduledForDeletion(): \Illuminate\Database\Eloquent\Collection
    {
        return User::whereNotNull('deletion_requested_at')
            ->where('will_be_deleted_at', '<=', now())
            ->get();
    }

    /**
     * Process pending deletions (called by scheduled job)
     */
    public function processPendingDeletions(): array
    {
        $usersToDelete = $this->getUsersScheduledForDeletion();
        $results = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($usersToDelete as $user) {
            $results['processed']++;
            
            $deletionResult = $this->permanentlyDeleteAccount($user);
            
            if ($deletionResult['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
                $results['errors'][] = [
                    'user_id' => $user->id,
                    'error' => $deletionResult['message'] ?? 'Unknown error',
                ];
            }
        }

        return $results;
    }
} 