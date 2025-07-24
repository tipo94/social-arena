<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\AccountDeletionMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendAccountDeletionNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user,
        protected string $type // 'requested', 'cancelled', 'completed'
    ) {
        $this->queue = config('mail.notifications.queue', 'emails');
        $this->delay = config('mail.notifications.delay', 0);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Don't send emails if user no longer exists (for completed deletions)
            if ($this->type !== 'completed' && !$this->user->exists) {
                Log::info('Account deletion notification skipped - user not found', [
                    'user_id' => $this->user->id,
                    'notification_type' => $this->type,
                ]);
                return;
            }

            // Skip if user has email notifications disabled (except for deletion completion)
            if ($this->type !== 'completed' && 
                $this->user->profile && 
                !$this->user->profile->email_notifications) {
                Log::info('Account deletion notification skipped - emails disabled', [
                    'user_id' => $this->user->id,
                    'notification_type' => $this->type,
                ]);
                return;
            }

            Mail::to($this->user->email)->send(new AccountDeletionMail($this->user, $this->type));

            Log::info('Account deletion notification sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'notification_type' => $this->type,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send account deletion notification', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'notification_type' => $this->type,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Account deletion notification job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'notification_type' => $this->type,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
} 