<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Follow extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'follower_id',
        'following_id',
        'followed_at',
        'is_muted',
        'show_notifications',
        'is_close_friend',
        'interaction_preferences',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'followed_at' => 'datetime',
        'is_muted' => 'boolean',
        'show_notifications' => 'boolean',
        'is_close_friend' => 'boolean',
        'interaction_preferences' => 'array',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Follow $follow) {
            if (!$follow->followed_at) {
                $follow->followed_at = now();
            }

            // Prevent self-following
            if ($follow->follower_id === $follow->following_id) {
                throw new \InvalidArgumentException('Users cannot follow themselves.');
            }
        });

        static::created(function (Follow $follow) {
            // Update follower/following counts
            $follow->updateCounts();
            
            // Create follow notification if notifications are enabled
            if ($follow->show_notifications) {
                $follow->createFollowNotification();
            }
        });

        static::deleted(function (Follow $follow) {
            // Update follower/following counts when unfollowed
            $follow->updateCounts(false);
        });
    }

    /**
     * Get the user who follows.
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    /**
     * Get the user being followed.
     */
    public function following(): BelongsTo
    {
        return $this->belongsTo(User::class, 'following_id');
    }

    /**
     * Check if the follow is active (not muted).
     */
    public function isActive(): bool
    {
        return !$this->is_muted && $this->show_notifications;
    }

    /**
     * Mute this follow (stop seeing posts in feed).
     */
    public function mute(): bool
    {
        return $this->update(['is_muted' => true]);
    }

    /**
     * Unmute this follow.
     */
    public function unmute(): bool
    {
        return $this->update(['is_muted' => false]);
    }

    /**
     * Mark as close friend.
     */
    public function markAsCloseFriend(): bool
    {
        return $this->update(['is_close_friend' => true]);
    }

    /**
     * Unmark as close friend.
     */
    public function unmarkAsCloseFriend(): bool
    {
        return $this->update(['is_close_friend' => false]);
    }

    /**
     * Toggle notifications for this follow.
     */
    public function toggleNotifications(): bool
    {
        return $this->update(['show_notifications' => !$this->show_notifications]);
    }

    /**
     * Check if the follower can modify this follow relationship.
     */
    public function canBeModifiedBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Only the follower can modify their follow settings
        return $user->id === $this->follower_id;
    }

    /**
     * Check if the follow relationship is visible to a user.
     */
    public function isVisibleTo(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Admin can see all
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // The follower and following user can always see
        if ($user->id === $this->follower_id || $user->id === $this->following_id) {
            return true;
        }

        // Others can see based on profile privacy settings
        return $this->following->isProfilePublic() && 
               ($this->following->profile->show_followers ?? true);
    }

    /**
     * Update follower/following counts.
     */
    public function updateCounts(bool $increment = true): void
    {
        try {
            $change = $increment ? 1 : -1;
            
            // Update follower's following count
            if ($this->follower && $this->follower->profile) {
                $currentFollowing = $this->follower->profile->following_count ?? 0;
                $this->follower->profile->updateQuietly([
                    'following_count' => max(0, $currentFollowing + $change)
                ]);
            }

            // Update following user's followers count
            if ($this->following && $this->following->profile) {
                $currentFollowers = $this->following->profile->followers_count ?? 0;
                $this->following->profile->updateQuietly([
                    'followers_count' => max(0, $currentFollowers + $change)
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the follow operation
            Log::warning('Failed to update follow counts: ' . $e->getMessage());
        }
    }

    /**
     * Create follow notification.
     */
    protected function createFollowNotification(): void
    {
        try {
            // Use the NotificationService to create a proper notification
            $notificationService = app(\App\Services\NotificationService::class);
            
            $notificationService->createFollowNotification(
                $this->follower,
                $this->following
            );
        } catch (\Exception $e) {
            Log::warning('Failed to create follow notification: ' . $e->getMessage());
        }
    }

    /**
     * Scope for active follows (not muted).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_muted', false);
    }

    /**
     * Scope for muted follows.
     */
    public function scopeMuted(Builder $query): Builder
    {
        return $query->where('is_muted', true);
    }

    /**
     * Scope for follows with notifications enabled.
     */
    public function scopeWithNotifications(Builder $query): Builder
    {
        return $query->where('show_notifications', true);
    }

    /**
     * Scope for close friend follows.
     */
    public function scopeCloseFriends(Builder $query): Builder
    {
        return $query->where('is_close_friend', true);
    }

    /**
     * Scope for follows by a specific user.
     */
    public function scopeByFollower(Builder $query, int $followerId): Builder
    {
        return $query->where('follower_id', $followerId);
    }

    /**
     * Scope for follows of a specific user.
     */
    public function scopeOfUser(Builder $query, int $followingId): Builder
    {
        return $query->where('following_id', $followingId);
    }

    /**
     * Scope for recent follows.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('followed_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for follows between two users.
     */
    public function scopeBetweenUsers(Builder $query, int $followerId, int $followingId): Builder
    {
        return $query->where('follower_id', $followerId)
                    ->where('following_id', $followingId);
    }

    /**
     * Get follow statistics for a user.
     */
    public static function getStatsForUser(User $user): array
    {
        $stats = [
            'total_following' => 0,
            'total_followers' => 0,
            'active_following' => 0,
            'muted_following' => 0,
            'close_friends' => 0,
            'recent_follows' => 0,
            'recent_followers' => 0,
        ];

        // Following stats
        $following = static::byFollower($user->id)->get();
        $stats['total_following'] = $following->count();
        $stats['active_following'] = $following->where('is_muted', false)->count();
        $stats['muted_following'] = $following->where('is_muted', true)->count();
        $stats['close_friends'] = $following->where('is_close_friend', true)->count();
        $stats['recent_follows'] = $following->where('followed_at', '>=', now()->subDays(7))->count();

        // Followers stats
        $followers = static::ofUser($user->id)->get();
        $stats['total_followers'] = $followers->count();
        $stats['recent_followers'] = $followers->where('followed_at', '>=', now()->subDays(7))->count();

        return $stats;
    }

    /**
     * Check if a user is following another user.
     */
    public static function isFollowing(User $follower, User $following): bool
    {
        return static::betweenUsers($follower->id, $following->id)->exists();
    }

    /**
     * Create or find a follow relationship.
     */
    public static function createFollow(User $follower, User $following, array $options = []): ?static
    {
        if ($follower->id === $following->id) {
            throw new \InvalidArgumentException('Users cannot follow themselves.');
        }

        // Check if already following
        $existingFollow = static::betweenUsers($follower->id, $following->id)->first();
        if ($existingFollow) {
            return $existingFollow;
        }

        return static::create(array_merge([
            'follower_id' => $follower->id,
            'following_id' => $following->id,
        ], $options));
    }

    /**
     * Remove a follow relationship.
     */
    public static function removeFollow(User $follower, User $following): bool
    {
        $follow = static::betweenUsers($follower->id, $following->id)->first();
        
        if (!$follow) {
            return false;
        }

        return $follow->delete();
    }
} 