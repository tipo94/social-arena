<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ContentVisibilityService
{
    /**
     * Available visibility levels for content.
     */
    const VISIBILITY_LEVELS = [
        'public' => 'Public - Everyone can see this',
        'friends' => 'Friends - Only friends can see this',
        'close_friends' => 'Close Friends - Only close friends can see this',
        'friends_of_friends' => 'Friends of Friends - Friends and their friends can see this',
        'group' => 'Group Members - Only group members can see this',
        'private' => 'Private - Only you can see this',
        'custom' => 'Custom - Specific people you choose',
    ];

    /**
     * Visibility levels for user profiles.
     */
    const PROFILE_VISIBILITY_LEVELS = [
        'public' => 'Public',
        'friends' => 'Friends Only',
        'friends_of_friends' => 'Friends of Friends',
        'private' => 'Private',
    ];

    /**
     * Who can interact options.
     */
    const INTERACTION_LEVELS = [
        'everyone' => 'Everyone',
        'friends' => 'Friends Only',
        'friends_of_friends' => 'Friends and their Friends',
        'close_friends' => 'Close Friends Only',
        'nobody' => 'Nobody',
    ];

    /**
     * Check if content is visible to a user.
     */
    public function isContentVisibleTo(
        string $contentType,
        $content,
        ?User $viewer = null,
        array $options = []
    ): bool {
        return match ($contentType) {
            'post' => $this->isPostVisibleTo($content, $viewer, $options),
            'profile' => $this->isProfileVisibleTo($content, $viewer, $options),
            'user_activity' => $this->isUserActivityVisibleTo($content, $viewer, $options),
            'user_content' => $this->isUserContentVisibleTo($content, $viewer, $options),
            default => false,
        };
    }

    /**
     * Check if a post is visible to a user.
     */
    public function isPostVisibleTo(Post $post, ?User $viewer = null, array $options = []): bool
    {
        // Hidden or reported posts are not visible (except to admins)
        if (($post->is_hidden || $post->is_reported) && !$this->isAdmin($viewer)) {
            return false;
        }

        // Scheduled posts are only visible if published
        if ($post->is_scheduled && !$post->isPublished()) {
            // Only post owner and admins can see unpublished scheduled posts
            return $viewer && ($viewer->id === $post->user_id || $this->isAdmin($viewer));
        }

        // Post owner can always see their own posts
        if ($viewer && $viewer->id === $post->user_id) {
            return true;
        }

        // Admins can see everything
        if ($this->isAdmin($viewer)) {
            return true;
        }

        // Check post visibility settings
        return match ($post->visibility) {
            'public' => true,
            'friends' => $this->areFriends($post->user, $viewer),
            'close_friends' => $this->areCloseFriends($post->user, $viewer),
            'friends_of_friends' => $this->areFriendsOrFriendsOfFriends($post->user, $viewer),
            'group' => $this->isGroupMember($post->group, $viewer),
            'private' => false,
            'custom' => $this->hasCustomAccess($post, $viewer, $options),
            default => false,
        };
    }

    /**
     * Check if a user profile is visible to another user.
     */
    public function isProfileVisibleTo(User $profileOwner, ?User $viewer = null, array $options = []): bool
    {
        // Self-access is always allowed
        if ($viewer && $viewer->id === $profileOwner->id) {
            return true;
        }

        // Admins can see everything
        if ($this->isAdmin($viewer)) {
            return true;
        }

        // Check profile visibility setting
        $visibility = $profileOwner->profile->profile_visibility ?? 'public';
        
        return match ($visibility) {
            'public' => true,
            'friends' => $this->areFriends($profileOwner, $viewer),
            'friends_of_friends' => $this->areFriendsOrFriendsOfFriends($profileOwner, $viewer),
            'private' => false,
            default => false,
        };
    }

    /**
     * Check if user activity is visible to another user.
     */
    public function isUserActivityVisibleTo(User $activityOwner, ?User $viewer = null, array $options = []): bool
    {
        $activityType = $options['activity_type'] ?? 'general';
        
        // Self-access is always allowed
        if ($viewer && $viewer->id === $activityOwner->id) {
            return true;
        }

        // Get the specific activity visibility setting
        $visibility = $this->getActivityVisibilitySetting($activityOwner, $activityType);
        
        return $this->checkVisibilityAccess($visibility, $activityOwner, $viewer);
    }

    /**
     * Check if user content is visible to another user.
     */
    public function isUserContentVisibleTo(User $contentOwner, ?User $viewer = null, array $options = []): bool
    {
        $contentType = $options['content_type'] ?? 'general';
        
        // Self-access is always allowed
        if ($viewer && $viewer->id === $contentOwner->id) {
            return true;
        }

        // Get the specific content visibility setting
        $visibility = $this->getContentVisibilitySetting($contentOwner, $contentType);
        
        return $this->checkVisibilityAccess($visibility, $contentOwner, $viewer);
    }

    /**
     * Filter a collection of posts based on visibility.
     */
    public function filterVisiblePosts(Collection $posts, ?User $viewer = null): Collection
    {
        return $posts->filter(function ($post) use ($viewer) {
            return $this->isPostVisibleTo($post, $viewer);
        });
    }

    /**
     * Apply visibility filter to a query builder for posts.
     */
    public function applyPostVisibilityFilter(Builder $query, ?User $viewer = null): Builder
    {
        // If no viewer, only show public posts
        if (!$viewer) {
            return $query->where('visibility', 'public')
                        ->where('is_hidden', false)
                        ->where('is_reported', false)
                        ->published();
        }

        return $query->where(function ($q) use ($viewer) {
            $q->where('user_id', $viewer->id) // User's own posts
              ->orWhere(function ($sq) use ($viewer) {
                  $sq->published()
                     ->where('is_hidden', false)
                     ->where('is_reported', false)
                     ->where(function ($visibilityQuery) use ($viewer) {
                         $visibilityQuery->where('visibility', 'public')
                                       ->orWhere(function ($friendsQuery) use ($viewer) {
                                           $friendsQuery->where('visibility', 'friends')
                                                       ->whereHas('user', function ($userQuery) use ($viewer) {
                                                           $userQuery->whereHas('sentFriendRequests', function ($fr) use ($viewer) {
                                                               $fr->where('friend_id', $viewer->id)->where('status', 'accepted');
                                                           })->orWhereHas('receivedFriendRequests', function ($fr) use ($viewer) {
                                                               $fr->where('user_id', $viewer->id)->where('status', 'accepted');
                                                           });
                                                       });
                                       })
                                       ->orWhere(function ($groupQuery) use ($viewer) {
                                           $groupQuery->where('visibility', 'group')
                                                     ->whereHas('group.memberships', function ($gm) use ($viewer) {
                                                         $gm->where('user_id', $viewer->id)->where('status', 'approved');
                                                     });
                                       });
                     });
              });
        });
    }

    /**
     * Get appropriate default visibility for a user's content.
     */
    public function getDefaultVisibilityForUser(User $user, string $contentType = 'post'): string
    {
        return match ($contentType) {
            'post' => $user->profile->post_visibility_default ?? 'friends',
            'activity' => $user->profile->reading_activity_visibility ?? 'friends',
            'profile' => $user->profile->profile_visibility ?? 'public',
            default => 'friends',
        };
    }

    /**
     * Get visibility options available to a user for specific content type.
     */
    public function getAvailableVisibilityOptions(User $user, string $contentType = 'post'): array
    {
        $baseOptions = [
            'public' => self::VISIBILITY_LEVELS['public'],
            'friends' => self::VISIBILITY_LEVELS['friends'],
            'private' => self::VISIBILITY_LEVELS['private'],
        ];

        // Add close friends if user has close friends feature
        if ($this->userHasCloseFriends($user)) {
            $baseOptions['close_friends'] = self::VISIBILITY_LEVELS['close_friends'];
        }

        // Add group option for certain content types
        if (in_array($contentType, ['post', 'activity'])) {
            $baseOptions['group'] = self::VISIBILITY_LEVELS['group'];
        }

        // Add friends of friends for profile-related content
        if (in_array($contentType, ['profile', 'activity'])) {
            $baseOptions['friends_of_friends'] = self::VISIBILITY_LEVELS['friends_of_friends'];
        }

        return $baseOptions;
    }

    /**
     * Get users who can see specific content.
     */
    public function getContentAudience(Post $post): array
    {
        return match ($post->visibility) {
            'public' => ['type' => 'everyone'],
            'friends' => [
                'type' => 'friends',
                'count' => $post->user->profile->friends_count ?? 0,
            ],
            'close_friends' => [
                'type' => 'close_friends',
                'count' => $this->getCloseFriendsCount($post->user),
            ],
            'group' => [
                'type' => 'group',
                'group_name' => $post->group?->name,
                'count' => $post->group?->members_count ?? 0,
            ],
            'private' => ['type' => 'only_me'],
            default => ['type' => 'unknown'],
        };
    }

    /**
     * Check if user can change visibility of content.
     */
    public function canChangeVisibility(User $user, $content, string $newVisibility): bool
    {
        // User must own the content
        if ($content->user_id !== $user->id) {
            return false;
        }

        // Check if new visibility option is available to user
        $availableOptions = $this->getAvailableVisibilityOptions($user, 'post');
        
        if (!array_key_exists($newVisibility, $availableOptions)) {
            return false;
        }

        // Additional checks for group visibility
        if ($newVisibility === 'group' && !$content->group_id) {
            return false;
        }

        return true;
    }

    /**
     * Update content visibility with validation.
     */
    public function updateContentVisibility($content, string $newVisibility, ?User $user = null): bool
    {
        if (!$user || !$this->canChangeVisibility($user, $content, $newVisibility)) {
            return false;
        }

        $content->update(['visibility' => $newVisibility]);
        
        return true;
    }

    /**
     * Check if two users are friends.
     */
    protected function areFriends(?User $user1, ?User $user2): bool
    {
        if (!$user1 || !$user2) {
            return false;
        }

        return $user1->isFriendsWith($user2);
    }

    /**
     * Check if two users are close friends.
     */
    protected function areCloseFriends(?User $user1, ?User $user2): bool
    {
        if (!$this->areFriends($user1, $user2)) {
            return false;
        }

        // Implementation for close friends would go here
        // For now, return false as this feature needs to be implemented
        return false;
    }

    /**
     * Check if users are friends or friends of friends.
     */
    protected function areFriendsOrFriendsOfFriends(?User $user1, ?User $user2): bool
    {
        if (!$user1 || !$user2) {
            return false;
        }

        // Direct friends
        if ($this->areFriends($user1, $user2)) {
            return true;
        }

        // Friends of friends
        return $user1->friends()
                     ->whereHas('friends', function ($query) use ($user2) {
                         $query->where('user_id', $user2->id);
                     })
                     ->exists();
    }

    /**
     * Check if user is a member of a group.
     */
    protected function isGroupMember(?Group $group, ?User $user): bool
    {
        if (!$group || !$user) {
            return false;
        }

        return $group->memberships()
                     ->where('user_id', $user->id)
                     ->where('status', 'approved')
                     ->exists();
    }

    /**
     * Check if user has custom access to content.
     */
    protected function hasCustomAccess($content, ?User $user, array $options = []): bool
    {
        // Implementation for custom access lists would go here
        // For now, return false as this feature needs to be implemented
        return false;
    }

    /**
     * Check if user is admin.
     */
    protected function isAdmin(?User $user): bool
    {
        return $user && $user->isAdmin();
    }

    /**
     * Get activity visibility setting for specific activity type.
     */
    protected function getActivityVisibilitySetting(User $user, string $activityType): string
    {
        return match ($activityType) {
            'reading' => $user->profile->reading_activity_visibility ?? 'friends',
            'online_status' => $user->profile->show_online_status ? 'public' : 'nobody',
            'last_activity' => $user->profile->show_last_activity ? 'friends' : 'nobody',
            default => 'friends',
        };
    }

    /**
     * Get content visibility setting for specific content type.
     */
    protected function getContentVisibilitySetting(User $user, string $contentType): string
    {
        return match ($contentType) {
            'posts' => $user->profile->who_can_see_posts ?? 'friends',
            'friends_list' => $user->profile->friends_list_visibility ?? 'friends',
            'book_lists' => $user->profile->book_lists_visibility ?? 'friends',
            'reviews' => $user->profile->reviews_visibility ?? 'public',
            'reading_goals' => $user->profile->reading_goals_visibility ?? 'friends',
            'reading_history' => $user->profile->reading_history_visibility ?? 'friends',
            default => 'friends',
        };
    }

    /**
     * Check visibility access based on visibility level and user relationship.
     */
    protected function checkVisibilityAccess(string $visibility, User $contentOwner, ?User $viewer): bool
    {
        return match ($visibility) {
            'public' => true,
            'friends' => $this->areFriends($contentOwner, $viewer),
            'close_friends' => $this->areCloseFriends($contentOwner, $viewer),
            'friends_of_friends' => $this->areFriendsOrFriendsOfFriends($contentOwner, $viewer),
            'private', 'nobody' => false,
            default => false,
        };
    }

    /**
     * Check if user has close friends feature enabled.
     */
    protected function userHasCloseFriends(User $user): bool
    {
        // Implementation for close friends feature detection
        // For now, return true to allow the option
        return true;
    }

    /**
     * Get count of close friends for a user.
     */
    protected function getCloseFriendsCount(User $user): int
    {
        // Implementation for close friends count
        // For now, return 0 as this feature needs to be implemented
        return 0;
    }

    /**
     * Get visibility statistics for a user.
     */
    public function getVisibilityStats(User $user): array
    {
        return [
            'total_posts' => $user->posts()->count(),
            'public_posts' => $user->posts()->where('visibility', 'public')->count(),
            'friends_posts' => $user->posts()->where('visibility', 'friends')->count(),
            'private_posts' => $user->posts()->where('visibility', 'private')->count(),
            'group_posts' => $user->posts()->where('visibility', 'group')->count(),
            'default_visibility' => $this->getDefaultVisibilityForUser($user),
            'profile_visibility' => $user->profile->profile_visibility ?? 'public',
        ];
    }

    /**
     * Bulk update visibility for multiple posts.
     */
    public function bulkUpdateVisibility(Collection $posts, string $newVisibility, User $user): array
    {
        $results = [
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($posts as $post) {
            if ($this->updateContentVisibility($post, $newVisibility, $user)) {
                $results['updated']++;
            } else {
                $results['failed']++;
                $results['errors'][] = "Post {$post->id}: " . $this->getUpdateFailureReason($post, $newVisibility, $user);
            }
        }

        return $results;
    }

    /**
     * Get reason why visibility update failed.
     */
    protected function getUpdateFailureReason($content, string $newVisibility, User $user): string
    {
        if ($content->user_id !== $user->id) {
            return 'You do not own this content';
        }

        $availableOptions = $this->getAvailableVisibilityOptions($user, 'post');
        if (!array_key_exists($newVisibility, $availableOptions)) {
            return 'Visibility option not available';
        }

        if ($newVisibility === 'group' && !$content->group_id) {
            return 'Cannot set group visibility for content not in a group';
        }

        return 'Unknown error';
    }
} 