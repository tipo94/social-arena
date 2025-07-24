<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrivacySettingsRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PrivacyController extends Controller
{
    /**
     * Get user's current privacy settings
     */
    public function getPrivacySettings(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load('profile');

        return response()->json([
            'success' => true,
            'data' => [
                'profile_privacy' => [
                    'is_private_profile' => $user->profile->is_private_profile,
                    'profile_visibility' => $user->profile->profile_visibility ?? 'public',
                    'contact_info_visibility' => $user->profile->contact_info_visibility ?? 'friends',
                    'location_visibility' => $user->profile->location_visibility ?? 'friends',
                    'birth_date_visibility' => $user->profile->birth_date_visibility ?? 'friends',
                    'search_visibility' => $user->profile->search_visibility ?? 'everyone',
                ],
                'activity_privacy' => [
                    'show_reading_activity' => $user->profile->show_reading_activity,
                    'show_online_status' => $user->profile->show_online_status ?? true,
                    'show_last_activity' => $user->profile->show_last_activity ?? true,
                    'reading_activity_visibility' => $user->profile->reading_activity_visibility ?? 'friends',
                    'post_visibility_default' => $user->profile->post_visibility_default ?? 'friends',
                ],
                'social_privacy' => [
                    'show_friends_list' => $user->profile->show_friends_list,
                    'show_mutual_friends' => $user->profile->show_mutual_friends ?? true,
                    'friends_list_visibility' => $user->profile->friends_list_visibility ?? 'friends',
                    'who_can_see_posts' => $user->profile->who_can_see_posts ?? 'friends',
                    'who_can_tag_me' => $user->profile->who_can_tag_me ?? 'friends',
                ],
                'interaction_privacy' => [
                    'allow_friend_requests' => $user->profile->allow_friend_requests,
                    'allow_group_invites' => $user->profile->allow_group_invites,
                    'allow_book_recommendations' => $user->profile->allow_book_recommendations,
                    'allow_messages_from' => $user->profile->allow_messages_from ?? 'friends',
                    'friend_request_visibility' => $user->profile->friend_request_visibility ?? 'everyone',
                    'who_can_find_me' => $user->profile->who_can_find_me ?? 'everyone',
                ],
                'content_privacy' => [
                    'book_lists_visibility' => $user->profile->book_lists_visibility ?? 'friends',
                    'reviews_visibility' => $user->profile->reviews_visibility ?? 'public',
                    'reading_goals_visibility' => $user->profile->reading_goals_visibility ?? 'friends',
                    'reading_history_visibility' => $user->profile->reading_history_visibility ?? 'friends',
                ],
            ],
        ]);
    }

    /**
     * Update user's privacy settings
     */
    public function updatePrivacySettings(UpdatePrivacySettingsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $validated = $request->validated();

            // Flatten all privacy settings into a single array for the profile update
            $privacyFields = [];
            
            foreach (['profile_privacy', 'activity_privacy', 'social_privacy', 'interaction_privacy', 'content_privacy'] as $category) {
                if (isset($validated[$category])) {
                    $privacyFields = array_merge($privacyFields, $validated[$category]);
                }
            }

            // Update the user profile with new privacy settings
            $user->profile->update($privacyFields);

            // Log privacy changes for audit
            $this->logPrivacyChanges($user, $privacyFields);

            $user->load('profile');

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => [
                    'updated_settings' => array_keys($privacyFields),
                    'timestamp' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update privacy settings',
                'errors' => ['system' => [$e->getMessage()]],
            ], 500);
        }
    }

    /**
     * Get privacy visibility options
     */
    public function getPrivacyOptions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'visibility_levels' => [
                    'public' => 'Everyone can see this',
                    'friends' => 'Only friends can see this',
                    'friends_of_friends' => 'Friends and their friends can see this',
                    'private' => 'Only you can see this',
                    'custom' => 'Custom privacy setting',
                ],
                'who_can_options' => [
                    'everyone' => 'Everyone',
                    'friends' => 'Friends only',
                    'friends_of_friends' => 'Friends and their friends',
                    'nobody' => 'Nobody',
                ],
                'post_visibility_options' => [
                    'public' => 'Public',
                    'friends' => 'Friends',
                    'close_friends' => 'Close friends',
                    'private' => 'Only me',
                ],
                'search_visibility_options' => [
                    'everyone' => 'Everyone can find me',
                    'friends_of_friends' => 'Friends and their friends',
                    'friends' => 'Friends only',
                    'nobody' => 'Nobody can find me',
                ],
            ],
        ]);
    }

    /**
     * Check what privacy level allows access to specific content
     */
    public function checkPrivacyAccess(Request $request): JsonResponse
    {
        $request->validate([
            'target_user_id' => ['required', 'integer', 'exists:users,id'],
            'content_type' => ['required', 'string', 'in:profile,contact_info,location,birth_date,friends_list,reading_activity,posts'],
        ]);

        $currentUser = $request->user();
        $targetUser = \App\Models\User::with('profile')->findOrFail($request->target_user_id);
        
        $hasAccess = $this->checkUserPrivacyAccess($currentUser, $targetUser, $request->content_type);

        return response()->json([
            'success' => true,
            'data' => [
                'has_access' => $hasAccess,
                'content_type' => $request->content_type,
                'target_user_id' => $targetUser->id,
                'relationship' => $this->getUserRelationship($currentUser, $targetUser),
            ],
        ]);
    }

    /**
     * Get privacy audit log for the current user
     */
    public function getPrivacyAuditLog(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Get recent privacy changes from logs (this would require a privacy_audit_logs table)
        // For now, return a placeholder structure
        return response()->json([
            'success' => true,
            'data' => [
                'recent_changes' => [],
                'privacy_score' => $this->calculatePrivacyScore($user),
                'recommendations' => $this->getPrivacyRecommendations($user),
            ],
        ]);
    }

    /**
     * Check if current user has access to target user's content based on privacy settings
     */
    protected function checkUserPrivacyAccess(\App\Models\User $currentUser, \App\Models\User $targetUser, string $contentType): bool
    {
        // Self-access is always allowed
        if ($currentUser->id === $targetUser->id) {
            return true;
        }

        // Get the specific privacy setting for this content type
        $privacySetting = $this->getPrivacySettingForContent($targetUser, $contentType);
        $relationship = $this->getUserRelationship($currentUser, $targetUser);

        return match ($privacySetting) {
            'public' => true,
            'friends' => $relationship === 'friends',
            'friends_of_friends' => in_array($relationship, ['friends', 'friends_of_friends']),
            'private', 'nobody' => false,
            default => false,
        };
    }

    /**
     * Get privacy setting for specific content type
     */
    protected function getPrivacySettingForContent(\App\Models\User $user, string $contentType): string
    {
        return match ($contentType) {
            'profile' => $user->profile->profile_visibility ?? 'public',
            'contact_info' => $user->profile->contact_info_visibility ?? 'friends',
            'location' => $user->profile->location_visibility ?? 'friends',
            'birth_date' => $user->profile->birth_date_visibility ?? 'friends',
            'friends_list' => $user->profile->friends_list_visibility ?? 'friends',
            'reading_activity' => $user->profile->reading_activity_visibility ?? 'friends',
            'posts' => $user->profile->who_can_see_posts ?? 'friends',
            default => 'friends',
        };
    }

    /**
     * Get relationship between two users
     */
    protected function getUserRelationship(\App\Models\User $currentUser, \App\Models\User $targetUser): string
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
    protected function getMutualFriendsCount(\App\Models\User $user1, \App\Models\User $user2): int
    {
        $user1Friends = $user1->friends()->pluck('id');
        $user2Friends = $user2->friends()->pluck('id');
        
        return $user1Friends->intersect($user2Friends)->count();
    }

    /**
     * Calculate privacy score for user (0-100)
     */
    protected function calculatePrivacyScore(\App\Models\User $user): int
    {
        $score = 0;
        $maxScore = 100;
        
        // Basic profile privacy (20 points)
        if ($user->profile->is_private_profile) $score += 20;
        
        // Contact info protection (15 points)
        if (in_array($user->profile->contact_info_visibility ?? 'friends', ['friends', 'private'])) $score += 15;
        
        // Location privacy (15 points)
        if (in_array($user->profile->location_visibility ?? 'friends', ['friends', 'private'])) $score += 15;
        
        // Reading activity privacy (10 points)
        if (!$user->profile->show_reading_activity) $score += 10;
        
        // Friends list privacy (10 points)
        if (!$user->profile->show_friends_list) $score += 10;
        
        // Search visibility (15 points)
        if (in_array($user->profile->search_visibility ?? 'everyone', ['friends', 'nobody'])) $score += 15;
        
        // Friend request restrictions (15 points)
        if (!$user->profile->allow_friend_requests) $score += 15;
        
        return min($score, $maxScore);
    }

    /**
     * Get privacy recommendations for user
     */
    protected function getPrivacyRecommendations(\App\Models\User $user): array
    {
        $recommendations = [];
        
        if ($user->profile->profile_visibility === 'public') {
            $recommendations[] = 'Consider making your profile visible to friends only for better privacy';
        }
        
        if ($user->profile->contact_info_visibility === 'public') {
            $recommendations[] = 'Hide your contact information from strangers';
        }
        
        if ($user->profile->show_reading_activity) {
            $recommendations[] = 'Consider hiding your reading activity for more privacy';
        }
        
        if ($user->profile->search_visibility === 'everyone') {
            $recommendations[] = 'Limit who can find you in search results';
        }
        
        return $recommendations;
    }

    /**
     * Log privacy setting changes for audit trail
     */
    protected function logPrivacyChanges(\App\Models\User $user, array $changes): void
    {
        // This would typically log to a privacy_audit_logs table
        // For now, we'll use the Laravel log
        \Illuminate\Support\Facades\Log::info('Privacy settings updated', [
            'user_id' => $user->id,
            'changed_settings' => array_keys($changes),
            'timestamp' => now(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
} 