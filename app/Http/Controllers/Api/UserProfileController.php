<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\UserService;
use App\Services\StorageService;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Http\Requests\UpdateInterestsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UserProfileController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected StorageService $storageService
    ) {}

    /**
     * Get user profile information
     */
    public function show(Request $request, ?int $userId = null): JsonResponse
    {
        try {
            $user = $userId ? User::findOrFail($userId) : $request->user();
            $requestingUser = $request->user();

            // Check privacy permissions
            if ($userId && $userId !== $requestingUser->id) {
                if (!$this->canViewProfile($user, $requestingUser)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Profile is private',
                        'errors' => ['permission' => ['This profile is private and cannot be viewed.']],
                    ], 403);
                }
            }

            $user->load('profile');

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $this->formatProfileResponse($user, $requestingUser),
                    'can_edit' => $userId === null || $userId === $requestingUser->id,
                    'is_friend' => $userId ? $requestingUser->isFriendsWith($user) : false,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile',
                'errors' => ['system' => [$e->getMessage()]],
            ], 404);
        }
    }

    /**
     * Update user profile information
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Update user basic information
            $userFields = array_intersect_key($validated, array_flip([
                'name', 'first_name', 'last_name', 'username', 'timezone', 'locale', 'theme'
            ]));

            if (!empty($userFields)) {
                $user->update($userFields);
            }

            // Update profile information
            $profileFields = array_intersect_key($validated, array_flip([
                'bio', 'location', 'website', 'birth_date', 'gender', 'occupation',
                'education', 'phone', 'social_links'
            ]));

            if (!empty($profileFields)) {
                $user->profile->update($profileFields);
            }

            // Update profile completion percentage
            $user->profile->calculateCompletionPercentage();

            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => [
                    'user' => $this->formatProfileResponse($user, $user),
                    'completion_percentage' => $user->profile->profile_completion_percentage,
                ],
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Update user avatar
     */
    public function updateAvatar(UpdateAvatarRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            if ($request->hasFile('avatar')) {
                // Delete old avatar if exists
                if ($user->profile->avatar_url) {
                    $this->storageService->deleteFile('public', $user->profile->avatar_url);
                }

                // Upload new avatar with options
                $avatarResult = $this->storageService->uploadImage(
                    $validated['avatar'],
                    'avatars',
                    [
                        'avatar' => [400, 400]
                    ]
                );
                
                $avatarPath = $avatarResult['avatar']['url'];

                $user->profile->update(['avatar_url' => $avatarPath]);

                // Update profile completion
                $user->profile->calculateCompletionPercentage();

                return response()->json([
                    'success' => true,
                    'message' => 'Avatar updated successfully',
                    'data' => [
                        'avatar_url' => $avatarPath,
                        'completion_percentage' => $user->profile->profile_completion_percentage,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No avatar file provided',
                'errors' => ['avatar' => ['Avatar file is required.']],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update avatar',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Delete user avatar
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->profile->avatar_url) {
                $this->storageService->deleteFile('public', $user->profile->avatar_url);
                $user->profile->update(['avatar_url' => null]);

                // Update profile completion
                $user->profile->calculateCompletionPercentage();

                return response()->json([
                    'success' => true,
                    'message' => 'Avatar deleted successfully',
                    'data' => [
                        'completion_percentage' => $user->profile->profile_completion_percentage,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No avatar to delete',
                'errors' => ['avatar' => ['No avatar currently set.']],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Update user cover image
     */
    public function updateCoverImage(Request $request): JsonResponse
    {
        $request->validate([
            'cover_image' => ['required', 'image', 'max:5120'], // 5MB max
        ]);

        try {
            $user = $request->user();

            // Delete old cover image if exists
            if ($user->profile->cover_image_url) {
                $this->storageService->deleteFile('public', $user->profile->cover_image_url);
            }

            // Upload new cover image
            $coverResult = $this->storageService->uploadImage(
                $request->file('cover_image'),
                'covers',
                [
                    'cover' => [1200, 400]
                ]
            );
            
            $coverPath = $coverResult['cover']['url'];

            $user->profile->update(['cover_image_url' => $coverPath]);

            return response()->json([
                'success' => true,
                'message' => 'Cover image updated successfully',
                'data' => [
                    'cover_image_url' => $coverPath,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update cover image',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Delete user cover image
     */
    public function deleteCoverImage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if ($user->profile->cover_image_url) {
                $this->storageService->deleteFile('public', $user->profile->cover_image_url);
                $user->profile->update(['cover_image_url' => null]);

                return response()->json([
                    'success' => true,
                    'message' => 'Cover image deleted successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'No cover image to delete',
                'errors' => ['cover_image' => ['No cover image currently set.']],
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete cover image',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Update user interests and preferences
     */
    public function updateInterests(UpdateInterestsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            $interestFields = array_intersect_key($validated, array_flip([
                'reading_preferences', 'favorite_genres', 'reading_goals', 'book_club_interests',
                'hobbies', 'professional_interests'
            ]));

            $user->profile->update($interestFields);

            // Update profile completion
            $user->profile->calculateCompletionPercentage();

            return response()->json([
                'success' => true,
                'message' => 'Interests updated successfully',
                'data' => [
                    'interests' => $user->profile->getReadingPreferences(),
                    'completion_percentage' => $user->profile->profile_completion_percentage,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interests',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Get user's reading preferences and interests
     */
    public function getInterests(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile');

        return response()->json([
            'success' => true,
            'data' => [
                'reading_preferences' => $user->profile->getReadingPreferences(),
                'available_genres' => $this->getAvailableGenres(),
                'available_reading_goals' => $this->getAvailableReadingGoals(),
            ],
        ]);
    }

    /**
     * Get profile completion status
     */
    public function getCompletionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile');

        $completion = $user->profile->calculateCompletionPercentage();
        
        return response()->json([
            'success' => true,
            'data' => [
                'completion_percentage' => $completion,
                'missing_fields' => $this->getMissingProfileFields($user),
                'suggestions' => $this->getProfileCompletionSuggestions($user),
            ],
        ]);
    }

    /**
     * Search users by profile criteria
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['sometimes', 'string', 'max:100'],
            'location' => ['sometimes', 'string', 'max:100'],
            'interests' => ['sometimes', 'array'],
            'age_min' => ['sometimes', 'integer', 'min:13', 'max:120'],
            'age_max' => ['sometimes', 'integer', 'min:13', 'max:120'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $currentUser = $request->user();
            $validated = $request->validated();

            $query = User::query()
                ->with('profile')
                ->where('is_active', true)
                ->where('is_banned', false)
                ->where('id', '!=', $currentUser->id);

            // Only show public profiles or friends
            $query->whereHas('profile', function ($q) use ($currentUser) {
                $q->where('is_private_profile', false)
                  ->orWhereHas('user.acceptedFriends', function ($friendQuery) use ($currentUser) {
                      $friendQuery->where('friend_id', $currentUser->id);
                  });
            });

            // Apply search filters
            if (!empty($validated['query'])) {
                $searchTerm = $validated['query'];
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('username', 'LIKE', "%{$searchTerm}%")
                      ->orWhereHas('profile', function ($profileQuery) use ($searchTerm) {
                          $profileQuery->where('bio', 'LIKE', "%{$searchTerm}%");
                      });
                });
            }

            if (!empty($validated['location'])) {
                $query->whereHas('profile', function ($q) use ($validated) {
                    $q->where('location', 'LIKE', "%{$validated['location']}%");
                });
            }

            $perPage = $validated['per_page'] ?? 20;
            $users = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                                    'users' => collect($users->items())->map(function ($user) use ($currentUser) {
                    return $this->formatProfileResponse($user, $currentUser);
                })->toArray(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Check if requesting user can view another user's profile
     */
    protected function canViewProfile(User $profileUser, User $requestingUser): bool
    {
        // Profile is public
        if (!$profileUser->profile->is_private_profile) {
            return true;
        }

        // User is viewing their own profile
        if ($profileUser->id === $requestingUser->id) {
            return true;
        }

        // Users are friends
        if ($requestingUser->isFriendsWith($profileUser)) {
            return true;
        }

        return false;
    }

    /**
     * Format profile response for API
     */
    protected function formatProfileResponse(User $user, User $requestingUser): array
    {
        $isOwnProfile = $user->id === $requestingUser->id;
        $relationship = $this->getUserRelationship($requestingUser, $user);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'full_name' => $user->full_name,
            'display_name' => $user->display_name,
            'email' => $isOwnProfile ? $user->email : null,
            'avatar_url' => $user->avatar_url,
            'is_online' => $this->canViewField($user, $requestingUser, 'online_status') ? $user->isOnline() : null,
            'last_activity_at' => $this->canViewField($user, $requestingUser, 'last_activity') ? $user->last_activity_at : null,
            'created_at' => $user->created_at,
            'profile' => [
                'bio' => $user->profile->bio,
                'location' => $this->canViewField($user, $requestingUser, 'location') ? $user->profile->location : null,
                'website' => $user->profile->website,
                'birth_date' => $this->canViewField($user, $requestingUser, 'birth_date') ? $user->profile->birth_date : null,
                'gender' => $this->canViewField($user, $requestingUser, 'birth_date') ? $user->profile->gender : null,
                'occupation' => $user->profile->occupation,
                'education' => $user->profile->education,
                'phone' => $this->canViewField($user, $requestingUser, 'contact_info') ? $user->profile->phone : null,
                'avatar_url' => $user->profile->avatar_url,
                'cover_image_url' => $user->profile->cover_image_url,
                'is_private_profile' => $user->profile->is_private_profile,
                'is_verified' => $user->profile->is_verified,
                'friends_count' => $this->canViewField($user, $requestingUser, 'friends_list') ? $user->profile->friends_count : null,
                'posts_count' => $user->profile->posts_count,
                'profile_completion_percentage' => $isOwnProfile ? $user->profile->profile_completion_percentage : null,
                'reading_preferences' => $this->canViewField($user, $requestingUser, 'reading_activity') ? $user->profile->getReadingPreferences() : null,
                'social_links' => $user->profile->social_links,
                'mutual_friends_count' => ($relationship === 'friends_of_friends' && $this->canViewField($user, $requestingUser, 'mutual_friends')) 
                    ? $this->getMutualFriendsCount($requestingUser, $user) : null,
            ],
            'privacy_context' => [
                'relationship' => $relationship,
                'can_send_friend_request' => $this->canSendFriendRequest($requestingUser, $user),
                'can_send_message' => $this->canSendMessage($requestingUser, $user),
                'can_see_reading_activity' => $this->canViewField($user, $requestingUser, 'reading_activity'),
                'can_see_friends_list' => $this->canViewField($user, $requestingUser, 'friends_list'),
            ],
        ];
    }

    /**
     * Get available book genres
     */
    protected function getAvailableGenres(): array
    {
        return [
            'fiction', 'non_fiction', 'mystery', 'romance', 'science_fiction', 'fantasy',
            'thriller', 'biography', 'history', 'self_help', 'business', 'technology',
            'health', 'cooking', 'travel', 'art', 'poetry', 'drama', 'philosophy',
            'religion', 'science', 'politics', 'sports', 'humor', 'children'
        ];
    }

    /**
     * Get available reading goals
     */
    protected function getAvailableReadingGoals(): array
    {
        return [
            'casual_reader', 'book_per_month', 'book_per_week', 'speed_reader',
            'quality_over_quantity', 'genre_explorer', 'author_completionist',
            'award_winners_only', 'classics_focus', 'new_releases_focus'
        ];
    }

    /**
     * Get missing profile fields for completion
     */
    protected function getMissingProfileFields(User $user): array
    {
        $missing = [];

        if (!$user->profile->avatar_url) $missing[] = 'avatar';
        if (!$user->profile->bio) $missing[] = 'bio';
        if (!$user->profile->location) $missing[] = 'location';
        if (!$user->profile->birth_date) $missing[] = 'birth_date';
        if (!$user->profile->occupation) $missing[] = 'occupation';
        if (!$user->profile->reading_preferences) $missing[] = 'reading_preferences';

        return $missing;
    }

    /**
     * Get profile completion suggestions
     */
    protected function getProfileCompletionSuggestions(User $user): array
    {
        $suggestions = [];

        if (!$user->profile->avatar_url) {
            $suggestions[] = 'Add a profile photo to help others recognize you';
        }
        if (!$user->profile->bio) {
            $suggestions[] = 'Write a bio to tell others about yourself';
        }
        if (!$user->profile->reading_preferences) {
            $suggestions[] = 'Set your reading preferences to get better book recommendations';
        }

        return $suggestions;
    }

    /**
     * Get relationship between two users
     */
    protected function getUserRelationship(User $currentUser, User $targetUser): string
    {
        if ($currentUser->isFriendsWith($targetUser)) {
            return 'friends';
        }

        // Check if they have mutual friends (friends_of_friends)
        $mutualFriendsCount = $this->getMutualFriendsCount($currentUser, $targetUser);
        if ($mutualFriendsCount > 0) {
            return 'friends_of_friends';
        }

        return 'strangers';
    }

    /**
     * Get count of mutual friends between two users
     */
    protected function getMutualFriendsCount(User $user1, User $user2): int
    {
        $user1Friends = $user1->friends()->pluck('id');
        $user2Friends = $user2->friends()->pluck('id');
        
        return $user1Friends->intersect($user2Friends)->count();
    }

    /**
     * Check if current user can view a specific field based on privacy settings
     */
    protected function canViewField(User $profileUser, User $requestingUser, string $fieldType): bool
    {
        // Self-access is always allowed
        if ($profileUser->id === $requestingUser->id) {
            return true;
        }

        // Get the privacy setting for this field type
        $privacySetting = $this->getPrivacySettingForField($profileUser, $fieldType);
        $relationship = $this->getUserRelationship($requestingUser, $profileUser);

        return match ($privacySetting) {
            'public' => true,
            'friends' => $relationship === 'friends',
            'friends_of_friends' => in_array($relationship, ['friends', 'friends_of_friends']),
            'private', 'nobody' => false,
            default => $relationship === 'friends', // Default to friends-only for unknown settings
        };
    }

    /**
     * Get privacy setting for specific field type
     */
    protected function getPrivacySettingForField(User $user, string $fieldType): string
    {
        return match ($fieldType) {
            'contact_info' => $user->profile->contact_info_visibility ?? 'friends',
            'location' => $user->profile->location_visibility ?? 'friends',
            'birth_date' => $user->profile->birth_date_visibility ?? 'friends',
            'friends_list' => $user->profile->friends_list_visibility ?? 'friends',
            'reading_activity' => $user->profile->reading_activity_visibility ?? 'friends',
            'online_status' => $user->profile->show_online_status ? 'public' : 'nobody',
            'last_activity' => $user->profile->show_last_activity ? 'public' : 'nobody',
            'mutual_friends' => $user->profile->show_mutual_friends ? 'friends_of_friends' : 'nobody',
            default => 'friends',
        };
    }

    /**
     * Check if requesting user can send friend request to target user
     */
    protected function canSendFriendRequest(User $requestingUser, User $targetUser): bool
    {
        // Can't send to self
        if ($requestingUser->id === $targetUser->id) {
            return false;
        }

        // Can't send if already friends
        if ($requestingUser->isFriendsWith($targetUser)) {
            return false;
        }

        // Can't send if already has pending request
        if ($requestingUser->hasPendingFriendRequestWith($targetUser)) {
            return false;
        }

        // Check target user's privacy settings
        if (!$targetUser->profile->allow_friend_requests) {
            return false;
        }

        // Check visibility settings for friend requests
        $visibility = $targetUser->profile->friend_request_visibility ?? 'everyone';
        $relationship = $this->getUserRelationship($requestingUser, $targetUser);

        return match ($visibility) {
            'everyone' => true,
            'friends_of_friends' => $relationship === 'friends_of_friends',
            'friends' => $relationship === 'friends', // This would only be true if they're already friends, which we checked above
            'nobody' => false,
            default => false,
        };
    }

    /**
     * Check if requesting user can send message to target user
     */
    protected function canSendMessage(User $requestingUser, User $targetUser): bool
    {
        // Can't message self
        if ($requestingUser->id === $targetUser->id) {
            return false;
        }

        $messagesSetting = $targetUser->profile->allow_messages_from ?? 'friends';
        $relationship = $this->getUserRelationship($requestingUser, $targetUser);

        return match ($messagesSetting) {
            'everyone' => true,
            'friends' => $relationship === 'friends',
            'friends_of_friends' => in_array($relationship, ['friends', 'friends_of_friends']),
            'nobody' => false,
            default => false,
        };
    }
} 