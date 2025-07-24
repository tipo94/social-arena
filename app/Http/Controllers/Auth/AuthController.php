<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Services\EmailService;
use App\Services\PasswordResetService;
use App\Http\Requests\RegisterRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected EmailService $emailService,
        protected PasswordResetService $passwordResetService
    ) {}

    /**
     * Register a new user
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            // Create user with profile
            $user = $this->userService->createUser($validated);

            // Update profile completion based on provided data
            $user->profile->calculateCompletionPercentage();

            // Send welcome email
            $this->emailService->sendWelcomeEmail($user);

            // Send email verification if email not verified
            if (!$user->email_verified_at) {
                $this->emailService->sendEmailVerification($user);
            }

            // Create authentication token
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

            return response()->json([
                'success' => true,
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 30 * 24 * 60 * 60, // 30 days in seconds
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed',
                'errors' => ['general' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
            'remember' => ['sometimes', 'boolean'],
            'device_name' => ['sometimes', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        // Check if user is banned
        if ($user->isBanned()) {
            return response()->json([
                'success' => false,
                'message' => 'Account is banned',
                'errors' => [
                    'account' => ['Your account has been banned.' . ($user->ban_reason ? ' Reason: ' . $user->ban_reason : '')],
                ],
            ], 403);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Account is deactivated',
                'errors' => [
                    'account' => ['Your account has been deactivated. Please contact support.'],
                ],
            ], 403);
        }

        // Update login statistics
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'login_count' => $user->login_count + 1,
        ]);

        // Update activity
        $user->updateActivity();

        // Create token
        $deviceName = $validated['device_name'] ?? $request->userAgent();
        $expiresAt = $validated['remember'] ?? false ? now()->addDays(30) : now()->addDay();
        
        $token = $user->createToken($deviceName, ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'user' => $this->formatUserResponse($user),
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $expiresAt->diffInSeconds(now()),
            ],
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out from all devices successfully',
        ]);
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->formatUserResponse($user),
                'stats' => $this->userService->getUserStats($user),
            ],
        ]);
    }

    /**
     * Refresh authentication token
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentToken = $request->user()->currentAccessToken();
        
        // Create new token
        $newToken = $user->createToken(
            $currentToken->name,
            $currentToken->abilities,
            now()->addDays(30)
        )->plainTextToken;

        // Delete old token
        $currentToken->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token refreshed successfully',
            'data' => [
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => 30 * 24 * 60 * 60,
            ],
        ]);
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $result = $this->passwordResetService->sendPasswordResetLink(
            $validated['email'],
            $request->ip(),
            $request->userAgent()
        );

        $statusCode = $result['success'] ? 200 : 422;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $result = $this->passwordResetService->resetPassword(
            $validated['email'],
            $validated['token'],
            $validated['password'],
            $request->ip(),
            $request->userAgent()
        );

        $statusCode = $result['success'] ? 200 : 422;
        
        return response()->json($result, $statusCode);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        try {
            $this->userService->changePassword(
                $request->user(),
                $validated['current_password'],
                $validated['password']
            );

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Password change failed',
                'errors' => ['current_password' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Verify email address
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'hash' => ['required', 'string'],
        ]);

        $user = User::findOrFail($validated['id']);

        if (!hash_equals(sha1($user->email), $validated['hash'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification link',
                'errors' => ['verification' => ['Invalid verification link']],
            ], 422);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        $user->markEmailAsVerified();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
        ]);
    }

    /**
     * Resend email verification
     */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
            ]);
        }

        // Check if user has requested verification recently (rate limiting)
        $lastVerificationSent = cache()->get("email_verification_sent_{$user->id}");
        if ($lastVerificationSent && now()->diffInMinutes($lastVerificationSent) < 5) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another verification email',
                'errors' => [
                    'rate_limit' => ['You can only request a verification email once every 5 minutes.']
                ],
                'data' => [
                    'wait_time' => 5 - now()->diffInMinutes($lastVerificationSent),
                ]
            ], 429);
        }

        $result = $this->emailService->sendEmailVerification($user);

        if ($result['success']) {
            // Cache the verification request time for rate limiting
            cache()->put("email_verification_sent_{$user->id}", now(), 300); // 5 minutes
        }

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => [
                'queued' => $result['queued'] ?? false,
                'rate_limited' => false,
            ]
        ]);
    }

    /**
     * Check if username is available
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
        ]);

        $exists = User::where('username', $validated['username'])->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'username' => $validated['username'],
                'available' => !$exists,
            ],
        ]);
    }

    /**
     * Check if email is available
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $exists = User::where('email', $validated['email'])->exists();

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $validated['email'],
                'available' => !$exists,
            ],
        ]);
    }

    /**
     * Format user response data
     */
    protected function formatUserResponse(User $user): array
    {
        $user->load('profile');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_url,
            'email_verified_at' => $user->email_verified_at,
            'is_online' => $user->isOnline(),
            'last_activity_at' => $user->last_activity_at,
            'account_type' => $user->account_type,
            'is_premium' => $user->isPremium(),
            'role' => $user->role,
            'timezone' => $user->timezone,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'created_at' => $user->created_at,
            'profile' => $user->profile ? [
                'bio' => $user->profile->bio,
                'location' => $user->profile->location,
                'website' => $user->profile->website,
                'birth_date' => $user->profile->birth_date,
                'avatar_url' => $user->profile->avatar_url,
                'cover_image_url' => $user->profile->cover_image_url,
                'is_private_profile' => $user->profile->is_private_profile,
                'profile_completion_percentage' => $user->profile->profile_completion_percentage,
                'is_verified' => $user->profile->is_verified,
                'friends_count' => $user->profile->friends_count,
                'posts_count' => $user->profile->posts_count,
            ] : null,
        ];
    }
}
