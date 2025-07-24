<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendPasswordResetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job can run.
     */
    public int $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected User $user,
        protected string $token
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
            // Check if user still exists
            if (!$this->user->exists) {
                Log::info('Password reset job skipped - user not found', [
                    'user_id' => $this->user->id,
                ]);
                return;
            }

            // Send the password reset email
            Mail::to($this->user->email)->send(new PasswordResetMail($this->user, $this->token));

            Log::info('Password reset email sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send password reset email', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Re-throw the exception to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Password reset email job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Optionally notify administrators about delivery issues
    }
} 