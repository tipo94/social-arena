<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Services\StorageService;
use App\Services\EmailService;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(
        protected StorageService $storageService,
        protected EmailService $emailService
    ) {}

    /**
     * Create a new user with profile.
     */
    public function createUser(array $userData): User
    {
        return DB::transaction(function () use ($userData) {
            // Generate username if not provided
            if (!isset($userData['username']) || !$userData['username']) {
                $userData['username'] = $this->generateUniqueUsername($userData['name']);
            }

            // Create the user
            $user = User::create([
                'name' => $userData['name'],
                'username' => $userData['username'],
                'first_name' => $userData['first_name'] ?? null,
                'last_name' => $userData['last_name'] ?? null,
                'email' => $userData['email'],
                'phone' => $userData['phone'] ?? null,
                'password' => isset($userData['password']) ? Hash::make($userData['password']) : null,
                'timezone' => $userData['timezone'] ?? 'UTC',
                'locale' => $userData['locale'] ?? 'en',
                'theme' => $userData['theme'] ?? 'auto',
                'social_provider' => $userData['social_provider'] ?? null,
                'social_provider_id' => $userData['social_provider_id'] ?? null,
                'social_avatar_url' => $userData['social_avatar_url'] ?? null,
                'account_type' => $userData['account_type'] ?? 'free',
            ]);

            // Update profile if additional profile data provided
            if (isset($userData['profile']) && is_array($userData['profile'])) {
                $user->profile->update($userData['profile']);
                $user->profile->calculateCompletionPercentage();
            }

            return $user->fresh(['profile']);
        });
    }

    /**
     * Update user basic information.
     */
    public function updateUser(User $user, array $userData): User
    {
        return DB::transaction(function () use ($user, $userData) {
            // Handle username change with validation
            if (isset($userData['username']) && $userData['username'] !== $user->username) {
                if (User::where('username', $userData['username'])->where('id', '!=', $user->id)->exists()) {
                    throw new \Exception('Username is already taken.');
                }
            }

            // Update user data
            $allowedFields = [
                'name', 'username', 'first_name', 'last_name', 'email', 'phone',
                'timezone', 'locale', 'theme'
            ];
            
            $updateData = array_intersect_key($userData, array_flip($allowedFields));
            $user->update($updateData);

            return $user->fresh();
        });
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(User $user, array $profileData): UserProfile
    {
        $profile = $user->profile;
        $profile->update($profileData);
        $profile->markAsUpdated();
        
        return $profile->fresh();
    }

    /**
     * Upload and set user avatar.
     */
    public function updateAvatar(User $user, UploadedFile $image): array
    {
        $results = $this->storageService->uploadImage($image, 'avatars', [
            'original' => null,
            'large' => [400, 400],
            'medium' => [200, 200],
            'small' => [100, 100],
        ]);

        // Update profile with new avatar URL
        $user->profile->update([
            'avatar_url' => $results['large']['url'],
        ]);
        $user->profile->markAsUpdated();

        return $results;
    }

    /**
     * Upload and set user cover image.
     */
    public function updateCoverImage(User $user, UploadedFile $image): array
    {
        $results = $this->storageService->uploadImage($image, 'avatars', [
            'original' => null,
            'cover' => [1200, 400],
            'cover_small' => [600, 200],
        ]);

        // Update profile with new cover image URL
        $user->profile->update([
            'cover_image_url' => $results['cover']['url'],
        ]);
        $user->profile->markAsUpdated();

        return $results;
    }

    /**
     * Change user password.
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            throw new \Exception('Current password is incorrect.');
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'last_password_change' => now()->toDateString(),
        ]);

        return true;
    }

    /**
     * Reset user password (for password reset flow).
     */
    public function resetPassword(User $user, string $newPassword): bool
    {
        $user->update([
            'password' => Hash::make($newPassword),
            'last_password_change' => now()->toDateString(),
        ]);

        return true;
    }

    /**
     * Ban a user.
     */
    public function banUser(User $user, string $reason, ?\DateTime $until = null): bool
    {
        $user->ban($reason, $until);
        
        // Send ban notification email
        $this->emailService->sendNotificationEmail($user, 'account_banned', [
            'reason' => $reason,
            'until' => $until?->format('Y-m-d H:i:s'),
        ]);

        return true;
    }

    /**
     * Unban a user.
     */
    public function unbanUser(User $user): bool
    {
        $user->unban();
        
        // Send unban notification email
        $this->emailService->sendNotificationEmail($user, 'account_unbanned', []);

        return true;
    }

    /**
     * Deactivate user account.
     */
    public function deactivateAccount(User $user): bool
    {
        $user->update(['is_active' => false]);
        return true;
    }

    /**
     * Reactivate user account.
     */
    public function reactivateAccount(User $user): bool
    {
        $user->update(['is_active' => true]);
        return true;
    }

    /**
     * Soft delete user account with data cleanup.
     */
    public function deleteAccount(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            // Soft delete the user (this will cascade to profile due to model setup)
            $user->delete();
            
            // Additional cleanup could be done here
            // - Remove from groups
            // - Delete posts or mark as anonymous
            // - Clean up friendships
            
            return true;
        });
    }

    /**
     * Permanently delete user account and all associated data.
     */
    public function permanentlyDeleteAccount(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            // Delete all user's data
            $user->posts()->delete();
            $user->sentFriendRequests()->delete();
            $user->receivedFriendRequests()->delete();
            $user->notifications()->delete();
            
            // Delete profile
            $user->profile()->delete();
            
            // Permanently delete user
            $user->forceDelete();
            
            return true;
        });
    }

    /**
     * Search users by various criteria.
     */
    public function searchUsers(array $criteria, int $perPage = 20): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = User::with('profile')
            ->where('is_active', true)
            ->where('is_banned', false);

        // Search by name/username
        if (isset($criteria['search']) && $criteria['search']) {
            $search = $criteria['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('username', 'LIKE', "%{$search}%")
                  ->orWhere('first_name', 'LIKE', "%{$search}%")
                  ->orWhere('last_name', 'LIKE', "%{$search}%");
            });
        }

        // Filter by location
        if (isset($criteria['location']) && $criteria['location']) {
            $query->whereHas('profile', function ($q) use ($criteria) {
                $q->where('location', 'LIKE', "%{$criteria['location']}%");
            });
        }

        // Filter by interests/genres
        if (isset($criteria['genres']) && is_array($criteria['genres'])) {
            $query->whereHas('profile', function ($q) use ($criteria) {
                foreach ($criteria['genres'] as $genre) {
                    $q->whereJsonContains('favorite_genres', $genre);
                }
            });
        }

        // Filter by account type
        if (isset($criteria['account_type']) && $criteria['account_type']) {
            $query->where('account_type', $criteria['account_type']);
        }

        // Filter by online status
        if (isset($criteria['online']) && $criteria['online']) {
            $query->where('is_online', true);
        }

        // Only public profiles or include private based on criteria
        if (!isset($criteria['include_private']) || !$criteria['include_private']) {
            $query->whereHas('profile', function ($q) {
                $q->where('is_private_profile', false);
            });
        }

        // Order by relevance/activity
        $orderBy = $criteria['order_by'] ?? 'activity';
        switch ($orderBy) {
            case 'alphabetical':
                $query->orderBy('name');
                break;
            case 'newest':
                $query->orderBy('created_at', 'desc');
                break;
            case 'activity':
            default:
                $query->orderBy('last_activity_at', 'desc')
                      ->orderBy('created_at', 'desc');
                break;
        }

        return $query->paginate($perPage);
    }

    /**
     * Get user statistics.
     */
    public function getUserStats(User $user): array
    {
        return [
            'posts_count' => $user->posts()->count(),
            'friends_count' => $user->profile->friends_count,
            'groups_count' => $user->profile->groups_count,
            'profile_completion' => $user->profile->profile_completion_percentage,
            'account_age_days' => $user->created_at->diffInDays(now()),
            'last_activity' => $user->last_activity_at,
            'is_online' => $user->isOnline(),
            'account_type' => $user->account_type,
            'is_premium' => $user->isPremium(),
        ];
    }

    /**
     * Get user recommendations (users they might want to connect with).
     */
    public function getUserRecommendations(User $user, int $limit = 10): \Illuminate\Database\Eloquent\Collection
    {
        // Get users with similar interests, location, or mutual friends
        return User::with('profile')
            ->where('id', '!=', $user->id)
            ->where('is_active', true)
            ->where('is_banned', false)
            ->whereHas('profile', function ($q) use ($user) {
                $q->where('is_private_profile', false)
                  ->where('allow_friend_requests', true);
                
                // Similar location
                if ($user->profile->location) {
                    $q->orWhere('location', 'LIKE', "%{$user->profile->location}%");
                }
                
                // Similar interests
                if ($user->profile->favorite_genres) {
                    foreach ($user->profile->favorite_genres as $genre) {
                        $q->orWhereJsonContains('favorite_genres', $genre);
                    }
                }
            })
            ->whereDoesntHave('sentFriendRequests', function ($q) use ($user) {
                $q->where('friend_id', $user->id);
            })
            ->whereDoesntHave('receivedFriendRequests', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }

    /**
     * Generate a unique username based on name.
     */
    protected function generateUniqueUsername(string $name): string
    {
        $baseUsername = Str::slug(strtolower($name), '');
        $username = $baseUsername;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Update user activity timestamp.
     */
    public function updateUserActivity(User $user): void
    {
        $user->updateActivity();
    }

    /**
     * Get online users count.
     */
    public function getOnlineUsersCount(): int
    {
        return User::where('is_online', true)
            ->where('is_active', true)
            ->count();
    }

    /**
     * Get recently active users.
     */
    public function getRecentlyActiveUsers(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return User::with('profile')
            ->where('is_active', true)
            ->where('is_banned', false)
            ->whereNotNull('last_activity_at')
            ->whereHas('profile', function ($q) {
                $q->where('is_private_profile', false);
            })
            ->orderBy('last_activity_at', 'desc')
            ->limit($limit)
            ->get();
    }
} 