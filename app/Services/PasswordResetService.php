<?php

namespace App\Services;

use App\Models\User;
use App\Jobs\SendPasswordResetJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetService
{
    /**
     * Rate limiting settings
     */
    protected const MAX_RESET_ATTEMPTS_PER_HOUR = 3;
    protected const MAX_RESET_ATTEMPTS_PER_DAY = 10;
    protected const RESET_REQUEST_COOLDOWN = 300; // 5 minutes in seconds

    /**
     * Send password reset link with enhanced security
     */
    public function sendPasswordResetLink(string $email, string $ipAddress, string $userAgent): array
    {
        try {
            // Check rate limiting
            $rateLimitResult = $this->checkRateLimit($email, $ipAddress);
            if (!$rateLimitResult['allowed']) {
                return $rateLimitResult;
            }

            $user = User::where('email', $email)->first();

            // Always return success for security (don't reveal if email exists)
            if (!$user) {
                // Log attempted reset for non-existent email
                $this->logPasswordResetAttempt($email, null, $ipAddress, $userAgent, 'email_not_found');
                
                return [
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.',
                ];
            }

            // Check if user account is banned or inactive
            if ($user->isBanned() || !$user->is_active) {
                $this->logPasswordResetAttempt($email, $user->id, $ipAddress, $userAgent, 'account_restricted');
                
                return [
                    'success' => true,
                    'message' => 'If the email exists, a password reset link has been sent.',
                ];
            }

            // Check cooldown period for this specific user
            $lastResetTime = Cache::get("password_reset_sent_{$user->id}");
            if ($lastResetTime && now()->diffInSeconds($lastResetTime) < self::RESET_REQUEST_COOLDOWN) {
                return [
                    'success' => false,
                    'message' => 'Please wait before requesting another password reset',
                    'errors' => [
                        'rate_limit' => ['You can only request a password reset once every 5 minutes.']
                    ],
                    'data' => [
                        'wait_time' => self::RESET_REQUEST_COOLDOWN - now()->diffInSeconds($lastResetTime),
                    ]
                ];
            }

            // Generate secure token
            $token = $this->generateSecureToken();

            // Store token in database with expiration and metadata
            $this->storePasswordResetToken($user, $token, $ipAddress, $userAgent);

            // Queue password reset email
            SendPasswordResetJob::dispatch($user, $token);

            // Update rate limiting caches
            $this->updateRateLimitCounters($email, $ipAddress, $user->id);

            // Log successful reset request
            $this->logPasswordResetAttempt($email, $user->id, $ipAddress, $userAgent, 'reset_sent');

            return [
                'success' => true,
                'message' => 'Password reset link sent to your email',
                'data' => [
                    'email' => $email,
                    'queued' => true,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Password reset service error', [
                'email' => $email,
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Unable to process password reset request',
                'errors' => ['system' => ['Please try again later.']],
            ];
        }
    }

    /**
     * Reset password with enhanced security validation
     */
    public function resetPassword(string $email, string $token, string $newPassword, string $ipAddress, string $userAgent): array
    {
        try {
            $user = User::where('email', $email)->first();

            if (!$user) {
                $this->logPasswordResetAttempt($email, null, $ipAddress, $userAgent, 'invalid_reset_attempt');
                
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token',
                    'errors' => ['token' => ['Invalid or expired token']],
                ];
            }

            // Verify token from database
            $resetRecord = DB::table('password_reset_tokens')
                ->where('email', $email)
                ->where('token', Hash::make($token))
                ->first();

            if (!$resetRecord) {
                $this->logPasswordResetAttempt($email, $user->id, $ipAddress, $userAgent, 'invalid_token');
                
                return [
                    'success' => false,
                    'message' => 'Invalid or expired reset token',
                    'errors' => ['token' => ['Invalid or expired token']],
                ];
            }

            // Check if token is expired (60 minutes)
            if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
                $this->logPasswordResetAttempt($email, $user->id, $ipAddress, $userAgent, 'expired_token');
                
                // Clean up expired token
                DB::table('password_reset_tokens')->where('email', $email)->delete();
                
                return [
                    'success' => false,
                    'message' => 'Reset token has expired',
                    'errors' => ['token' => ['Reset token has expired. Please request a new one.']],
                ];
            }

            // Validate that new password is different from current
            if ($user->password && Hash::check($newPassword, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'New password must be different from current password',
                    'errors' => ['password' => ['New password must be different from your current password.']],
                ];
            }

            // Reset password
            $user->update([
                'password' => Hash::make($newPassword),
                'last_password_change' => now(),
            ]);

            // Revoke all existing tokens for security
            $user->tokens()->delete();

            // Clean up reset tokens
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            // Clear rate limiting caches
            $this->clearUserRateLimitCaches($user->id, $email);

            // Log successful password reset
            $this->logPasswordResetAttempt($email, $user->id, $ipAddress, $userAgent, 'password_reset_success');

            return [
                'success' => true,
                'message' => 'Password reset successfully',
                'data' => [
                    'tokens_revoked' => true,
                    'require_new_login' => true,
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Password reset error', [
                'email' => $email,
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Password reset failed',
                'errors' => ['system' => ['Unable to reset password. Please try again.']],
            ];
        }
    }

    /**
     * Check rate limiting for password reset requests
     */
    protected function checkRateLimit(string $email, string $ipAddress): array
    {
        $emailKey = "password_reset_email_{$email}";
        $ipKey = "password_reset_ip_{$ipAddress}";

        $emailAttempts = Cache::get($emailKey, 0);
        $ipAttempts = Cache::get($ipKey, 0);

        if ($emailAttempts >= self::MAX_RESET_ATTEMPTS_PER_DAY) {
            return [
                'success' => false,
                'message' => 'Too many password reset attempts',
                'errors' => ['rate_limit' => ['You have exceeded the daily limit for password reset requests.']],
                'allowed' => false,
            ];
        }

        if ($ipAttempts >= self::MAX_RESET_ATTEMPTS_PER_HOUR) {
            return [
                'success' => false,
                'message' => 'Too many password reset attempts',
                'errors' => ['rate_limit' => ['Too many attempts from this IP address. Please try again later.']],
                'allowed' => false,
            ];
        }

        return ['allowed' => true];
    }

    /**
     * Update rate limiting counters
     */
    protected function updateRateLimitCounters(string $email, string $ipAddress, int $userId): void
    {
        $emailKey = "password_reset_email_{$email}";
        $ipKey = "password_reset_ip_{$ipAddress}";
        $userKey = "password_reset_sent_{$userId}";

        // Increment counters
        Cache::increment($emailKey, 1);
        Cache::increment($ipKey, 1);

        // Set expiration times
        Cache::put($emailKey, Cache::get($emailKey), now()->addDay());
        Cache::put($ipKey, Cache::get($ipKey), now()->addHour());
        Cache::put($userKey, now(), self::RESET_REQUEST_COOLDOWN);
    }

    /**
     * Clear rate limiting caches for user
     */
    protected function clearUserRateLimitCaches(int $userId, string $email): void
    {
        Cache::forget("password_reset_sent_{$userId}");
        Cache::forget("password_reset_email_{$email}");
    }

    /**
     * Generate secure token
     */
    protected function generateSecureToken(): string
    {
        return Str::random(64);
    }

    /**
     * Store password reset token in database
     */
    protected function storePasswordResetToken(User $user, string $token, string $ipAddress, string $userAgent): void
    {
        // Clean up old tokens for this email
        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Store new token
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        // Store additional metadata in cache for audit purposes
        Cache::put("password_reset_meta_{$token}", [
            'user_id' => $user->id,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ], 3600); // 1 hour
    }

    /**
     * Log password reset attempt for audit purposes
     */
    protected function logPasswordResetAttempt(string $email, ?int $userId, string $ipAddress, string $userAgent, string $action): void
    {
        Log::info('Password reset attempt', [
            'action' => $action,
            'email' => $email,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get password reset statistics for admin
     */
    public function getResetStatistics(): array
    {
        return [
            'total_requests_today' => 0, // Would be implemented with proper logging table
            'successful_resets_today' => 0,
            'failed_attempts_today' => 0,
            'most_active_ips' => [],
        ];
    }
} 