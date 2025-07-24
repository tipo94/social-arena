<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Mail\Mailable;
use App\Models\User;

class EmailService
{
    /**
     * Send email with tracking and error handling
     */
    public function sendEmail(string $to, Mailable $mailable, ?User $user = null): array
    {
        try {
            Mail::to($to)->send($mailable);
            
            $this->logEmailSent($to, get_class($mailable), $user);
            
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'to' => $to,
                'mailable' => get_class($mailable),
            ];
        } catch (\Exception $e) {
            $this->logEmailError($to, get_class($mailable), $e->getMessage(), $user);
            
            return [
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
                'to' => $to,
                'mailable' => get_class($mailable),
            ];
        }
    }

    /**
     * Queue email for later delivery
     */
    public function queueEmail(string $to, Mailable $mailable, ?int $delay = null): array
    {
        try {
            $mail = Mail::to($to);
            
            if ($delay) {
                $mail->later(now()->addSeconds($delay), $mailable);
            } else {
                $mail->queue($mailable);
            }
            
            return [
                'success' => true,
                'message' => 'Email queued successfully',
                'to' => $to,
                'mailable' => get_class($mailable),
                'delay' => $delay,
            ];
        } catch (\Exception $e) {
            $this->logEmailError($to, get_class($mailable), $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to queue email',
                'error' => $e->getMessage(),
                'to' => $to,
                'mailable' => get_class($mailable),
            ];
        }
    }

    /**
     * Send bulk emails
     */
    public function sendBulkEmail(array $recipients, Mailable $mailable): array
    {
        $results = [
            'sent' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            $user = is_array($recipient) && isset($recipient['user']) ? $recipient['user'] : null;
            
            $result = $this->sendEmail($email, $mailable, $user);
            
            if ($result['success']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result;
            }
        }

        return $results;
    }

    /**
     * Queue bulk emails
     */
    public function queueBulkEmail(array $recipients, Mailable $mailable, ?int $delay = null): array
    {
        $results = [
            'queued' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($recipients as $recipient) {
            $email = is_array($recipient) ? $recipient['email'] : $recipient;
            
            $result = $this->queueEmail($email, $mailable, $delay);
            
            if ($result['success']) {
                $results['queued']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $result;
            }
        }

        return $results;
    }

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user): array
    {
        return $this->queueEmail(
            $user->email,
            new \App\Mail\WelcomeEmail($user)
        );
    }

    /**
     * Send email verification
     */
    public function sendEmailVerification(User $user): array
    {
        try {
            // Dispatch job to queue for async processing
            \App\Jobs\SendEmailVerificationJob::dispatch($user);
            
            $this->logEmailQueued($user->email, 'EmailVerificationMail', $user);
            
            return [
                'success' => true,
                'message' => 'Email verification queued successfully',
                'to' => $user->email,
                'type' => 'email_verification',
                'queued' => true,
            ];
        } catch (\Exception $e) {
            $this->logEmailError($user->email, 'EmailVerificationMail', $e->getMessage(), $user);
            
            return [
                'success' => false,
                'message' => 'Failed to queue email verification',
                'error' => $e->getMessage(),
                'to' => $user->email,
                'type' => 'email_verification',
            ];
        }
    }

    /**
     * Send password reset email
     */
    public function sendPasswordReset(User $user, string $token): array
    {
        return $this->sendEmail(
            $user->email,
            new \App\Mail\PasswordResetMail($user, $token)
        );
    }

    /**
     * Send notification email
     */
    public function sendNotificationEmail(User $user, string $type, array $data): array
    {
        return $this->queueEmail(
            $user->email,
            new \App\Mail\NotificationMail($user, $type, $data)
        );
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(string $testEmail): array
    {
        try {
            return $this->sendEmail(
                $testEmail,
                new \App\Mail\TestEmail()
            );
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Email configuration test failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get email delivery statistics
     */
    public function getEmailStats(?string $period = '24h'): array
    {
        // This would typically query a database table for email logs
        // For now, returning a placeholder structure
        return [
            'period' => $period,
            'total_sent' => 0,
            'total_failed' => 0,
            'success_rate' => 100,
            'most_common_errors' => [],
            'delivery_time_avg' => 0,
        ];
    }

    /**
     * Log successful email delivery
     */
    protected function logEmailSent(string $to, string $mailable, ?User $user = null): void
    {
        Log::info('Email sent successfully', [
            'to' => $to,
            'mailable' => $mailable,
            'user_id' => $user?->id,
            'timestamp' => now(),
        ]);
    }

    /**
     * Log email delivery error
     */
    protected function logEmailError(string $to, string $mailable, string $error, ?User $user = null): void
    {
        Log::error('Email delivery failed', [
            'to' => $to,
            'mailable' => $mailable,
            'error' => $error,
            'user_id' => $user?->id,
            'timestamp' => now(),
        ]);
    }

    /**
     * Log email queued
     */
    protected function logEmailQueued(string $to, string $mailable, ?User $user = null): void
    {
        Log::info('Email queued successfully', [
            'to' => $to,
            'mailable' => $mailable,
            'user_id' => $user?->id,
            'timestamp' => now()->toISOString(),
        ]);
    }
} 