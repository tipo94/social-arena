<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\EmailVerificationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationJob implements ShouldQueue
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
        protected User $user
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
            // Check if user still exists and email is not verified
            if (!$this->user->exists || $this->user->hasVerifiedEmail()) {
                Log::info('Email verification job skipped', [
                    'user_id' => $this->user->id,
                    'reason' => $this->user->exists ? 'email_already_verified' : 'user_not_found'
                ]);
                return;
            }

            // Send the verification email
            Mail::to($this->user->email)->send(new EmailVerificationMail($this->user));

            Log::info('Email verification sent successfully', [
                'user_id' => $this->user->id,
                'email' => $this->user->email
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to send email verification', [
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
        Log::error('Email verification job failed permanently', [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        // Optionally notify administrators or set a flag on the user model
        // to indicate email delivery issues
    }
} 