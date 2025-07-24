<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAccountDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Only try once for account deletion
    public int $timeout = 300; // 5 minutes timeout for deletion process

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user
    ) {
        $this->queue = 'critical'; // Use critical queue for account deletions
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Verify the user still exists and deletion is still requested
            if (!$this->user->exists) {
                Log::info('Account deletion job skipped - user no longer exists', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // Check if deletion was cancelled
            if (!$this->user->deletion_requested_at) {
                Log::info('Account deletion job skipped - deletion was cancelled', [
                    'user_id' => $this->user->id,
                    'email' => $this->user->email,
                ]);
                return;
            }

            // Verify it's time to delete (grace period has passed)
            if ($this->user->will_be_deleted_at && $this->user->will_be_deleted_at->isFuture()) {
                Log::warning('Account deletion job executed too early', [
                    'user_id' => $this->user->id,
                    'scheduled_deletion' => $this->user->will_be_deleted_at,
                    'current_time' => now(),
                ]);
                
                // Reschedule for the correct time
                self::dispatch($this->user)->delay($this->user->will_be_deleted_at);
                return;
            }

            // Proceed with deletion
            $deletionService = app(AccountDeletionService::class);
            
            Log::info('Starting account deletion process', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'deletion_requested_at' => $this->user->deletion_requested_at,
            ]);

            // Send pre-deletion notification (final chance to cancel)
            SendAccountDeletionNotificationJob::dispatch($this->user, 'final_warning')
                ->delay(now()->subMinutes(5)); // Send 5 minutes before actual deletion

            // Wait a moment then proceed with deletion
            sleep(60); // Wait 1 minute before final deletion

            // Refresh user data in case anything changed
            $this->user->refresh();

            // Final check - ensure deletion wasn't cancelled in the last minute
            if (!$this->user->deletion_requested_at) {
                Log::info('Account deletion cancelled at the last minute', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // Store user info for post-deletion notification
            $userEmail = $this->user->email;
            $userName = $this->user->name;
            $userId = $this->user->id;

            // Perform the actual deletion
            $result = $deletionService->permanentlyDeleteAccount($this->user);

            if ($result['success']) {
                Log::info('Account deletion job completed successfully', [
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'deletion_summary' => $result['data']['deletion_summary'] ?? null,
                ]);

                // Send completion notification to a backup email or admin
                // (User email no longer exists, so this would go to admin/compliance)
                if (config('app.deletion_notification_email')) {
                    // Send anonymized deletion confirmation to admin
                    Log::info('Account deletion completed - admin notification would be sent');
                }
            } else {
                Log::error('Account deletion job failed', [
                    'user_id' => $userId,
                    'email' => $userEmail,
                    'error' => $result['message'] ?? 'Unknown error',
                ]);

                throw new \Exception($result['message'] ?? 'Account deletion failed');
            }

        } catch (\Exception $e) {
            Log::error('Account deletion job encountered an error', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Account deletion job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark user for manual review if automated deletion fails
        try {
            if ($this->user->exists) {
                $this->user->update([
                    'deletion_failed_at' => now(),
                    'deletion_failure_reason' => $exception->getMessage(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to mark user for manual deletion review', [
                'user_id' => $this->user->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Notify administrators about the failed deletion
        if (config('app.admin_notification_email')) {
            Log::critical('MANUAL INTERVENTION REQUIRED: Account deletion failed', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'failure_reason' => $exception->getMessage(),
                'requires_manual_deletion' => true,
            ]);
        }
    }
} 