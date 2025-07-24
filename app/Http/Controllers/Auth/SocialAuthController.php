<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use App\Services\EmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Exception;

class SocialAuthController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected EmailService $emailService
    ) {}

    /**
     * Redirect to OAuth provider
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        try {
            $this->validateProvider($provider);
            
            $redirectUrl = Socialite::driver($provider)->redirect()->getTargetUrl();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'redirect_url' => $redirectUrl,
                    'provider' => $provider,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'OAuth redirection failed',
                'errors' => ['provider' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Handle OAuth callback
     */
    public function handleProviderCallback(string $provider, Request $request): JsonResponse
    {
        try {
            $this->validateProvider($provider);
            
            $providerUser = Socialite::driver($provider)->user();
            
            // Find existing user by social provider ID or email
            $user = User::where('social_provider', $provider)
                       ->where('social_provider_id', $providerUser->getId())
                       ->first();
            
            if (!$user) {
                $user = User::where('email', $providerUser->getEmail())->first();
                
                if ($user) {
                    // Link existing account with social provider
                    $user->update([
                        'social_provider' => $provider,
                        'social_provider_id' => $providerUser->getId(),
                        'social_avatar_url' => $providerUser->getAvatar(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ]);
                } else {
                    // Create new user from social provider
                    $user = $this->createUserFromProvider($provider, $providerUser);
                }
            } else {
                // Update existing social user data
                $user->update([
                    'social_avatar_url' => $providerUser->getAvatar(),
                    'last_login_at' => now(),
                    'last_login_ip' => $request->ip(),
                    'login_count' => $user->login_count + 1,
                ]);
            }

            // Update user activity
            $user->updateActivity();

            // Create authentication token
            $token = $user->createToken('auth_token', ['*'], now()->addDays(30))->plainTextToken;

            // Send welcome email for new users
            if ($user->wasRecentlyCreated) {
                $this->emailService->sendWelcomeEmail($user);
            }

            return response()->json([
                'success' => true,
                'message' => 'Social login successful',
                'data' => [
                    'user' => $this->formatUserResponse($user),
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => 30 * 24 * 60 * 60, // 30 days in seconds
                    'provider' => $provider,
                    'is_new_user' => $user->wasRecentlyCreated,
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Social authentication failed',
                'errors' => ['auth' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Link social account to existing user
     */
    public function linkAccount(string $provider, Request $request): JsonResponse
    {
        try {
            $this->validateProvider($provider);
            
            $user = $request->user();
            
            // Check if user already has this provider linked
            if ($user->social_provider === $provider && $user->social_provider_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Account already linked to this provider',
                    'errors' => ['provider' => ['This social account is already linked.']],
                ], 422);
            }

            $providerUser = Socialite::driver($provider)->user();
            
            // Check if this social account is already linked to another user
            $existingUser = User::where('social_provider', $provider)
                              ->where('social_provider_id', $providerUser->getId())
                              ->where('id', '!=', $user->id)
                              ->first();
            
            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Social account already linked to another user',
                    'errors' => ['provider' => ['This social account is already linked to another user.']],
                ], 422);
            }

            // Link the account
            $user->update([
                'social_provider' => $provider,
                'social_provider_id' => $providerUser->getId(),
                'social_avatar_url' => $providerUser->getAvatar(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Social account linked successfully',
                'data' => [
                    'provider' => $provider,
                    'provider_name' => $providerUser->getName(),
                    'provider_email' => $providerUser->getEmail(),
                ],
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to link social account',
                'errors' => ['auth' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Unlink social account from user
     */
    public function unlinkAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->social_provider || !$user->social_provider_id) {
            return response()->json([
                'success' => false,
                'message' => 'No social account linked',
                'errors' => ['provider' => ['No social account is currently linked.']],
            ], 422);
        }

        // Ensure user has a password before unlinking social account
        if (!$user->password) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot unlink social account',
                'errors' => ['security' => ['Please set a password before unlinking your social account.']],
            ], 422);
        }

        $provider = $user->social_provider;

        $user->update([
            'social_provider' => null,
            'social_provider_id' => null,
            'social_avatar_url' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Social account unlinked successfully',
            'data' => [
                'provider' => $provider,
            ],
        ]);
    }

    /**
     * Get user's social account information
     */
    public function getSocialAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'has_social_account' => !empty($user->social_provider),
                'provider' => $user->social_provider,
                'provider_id' => $user->social_provider_id,
                'social_avatar_url' => $user->social_avatar_url,
                'can_unlink' => !empty($user->password), // Can only unlink if user has password
            ],
        ]);
    }

    /**
     * Create user from social provider data
     */
    protected function createUserFromProvider(string $provider, $providerUser): User
    {
        $userData = [
            'name' => $providerUser->getName(),
            'email' => $providerUser->getEmail(),
            'email_verified_at' => now(), // Social providers verify emails
            'social_provider' => $provider,
            'social_provider_id' => $providerUser->getId(),
            'social_avatar_url' => $providerUser->getAvatar(),
            'account_type' => 'free',
        ];

        // Generate username from email if not provided by provider
        if (!isset($userData['username'])) {
            $emailPrefix = explode('@', $providerUser->getEmail())[0];
            $userData['username'] = $this->generateUniqueUsername($emailPrefix);
        }

        // Split name into first and last name if possible
        $nameParts = explode(' ', $providerUser->getName(), 2);
        $userData['first_name'] = $nameParts[0] ?? null;
        $userData['last_name'] = $nameParts[1] ?? null;

        return $this->userService->createUser($userData);
    }

    /**
     * Generate unique username
     */
    protected function generateUniqueUsername(string $base): string
    {
        $username = Str::slug($base, '');
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = Str::slug($base, '') . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Validate OAuth provider
     */
    protected function validateProvider(string $provider): void
    {
        $allowedProviders = ['google', 'github'];
        
        if (!in_array($provider, $allowedProviders)) {
            throw new Exception("Provider '{$provider}' is not supported");
        }
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
            'social_provider' => $user->social_provider,
            'has_password' => !empty($user->password),
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