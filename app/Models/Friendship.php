<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class Friendship extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Friendship status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACCEPTED = 'accepted';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_DECLINED = 'declined';

    /**
     * Maximum mutual friends to calculate for performance.
     */
    const MAX_MUTUAL_FRIENDS_CALCULATION = 1000;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'friend_id',
        'status',
        'requested_at',
        'accepted_at',
        'blocked_at',
        'can_see_posts',
        'can_send_messages',
        'show_in_friends_list',
        'mutual_friends_count',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'requested_at' => 'datetime',
        'accepted_at' => 'datetime',
        'blocked_at' => 'datetime',
        'can_see_posts' => 'boolean',
        'can_send_messages' => 'boolean',
        'show_in_friends_list' => 'boolean',
        'mutual_friends_count' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_pending',
        'is_accepted',
        'is_blocked',
        'friendship_duration',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Friendship $friendship) {
            if (!$friendship->requested_at) {
                $friendship->requested_at = now();
            }
        });

        static::updated(function (Friendship $friendship) {
            // Update timestamps based on status changes
            if ($friendship->isDirty('status')) {
                $updateData = [];
                
                switch ($friendship->status) {
                    case self::STATUS_ACCEPTED:
                        if (!$friendship->accepted_at) {
                            $updateData['accepted_at'] = now();
                        }
                        break;
                    case self::STATUS_BLOCKED:
                        if (!$friendship->blocked_at) {
                            $updateData['blocked_at'] = now();
                        }
                        break;
                }
                
                // Update mutual friends count when friendship is accepted
                if ($friendship->status === self::STATUS_ACCEPTED) {
                    $mutualCount = $friendship->calculateMutualFriendsCount();
                    $updateData['mutual_friends_count'] = $mutualCount;
                }
                
                // Update all data quietly to avoid triggering events again
                if (!empty($updateData)) {
                    $friendship->updateQuietly($updateData);
                }
            }
        });

        static::created(function (Friendship $friendship) {
            // Create notification for friend request
            if ($friendship->status === self::STATUS_PENDING) {
                $friendship->createFriendRequestNotification();
            }
        });
    }

    /**
     * Get the user who sent the friend request.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who received the friend request.
     */
    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    /**
     * Check if friendship is pending.
     */
    public function getIsPendingAttribute(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if friendship is accepted.
     */
    public function getIsAcceptedAttribute(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Check if friendship is blocked.
     */
    public function getIsBlockedAttribute(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    /**
     * Get friendship duration in days.
     */
    public function getFriendshipDurationAttribute(): ?int
    {
        if ($this->status !== self::STATUS_ACCEPTED || !$this->accepted_at) {
            return null;
        }

        return $this->accepted_at->diffInDays(now());
    }

    /**
     * Accept the friend request.
     */
    public function accept(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);

        // Create acceptance notification
        $this->createFriendshipAcceptedNotification();

        return true;
    }

    /**
     * Decline the friend request.
     */
    public function decline(): bool
    {
        if ($this->status !== self::STATUS_PENDING) {
            return false;
        }

        $this->update(['status' => self::STATUS_DECLINED]);
        return true;
    }

    /**
     * Block the user.
     */
    public function block(): bool
    {
        $this->update([
            'status' => self::STATUS_BLOCKED,
            'blocked_at' => now(),
        ]);

        return true;
    }

    /**
     * Unblock the user (reset to declined status).
     */
    public function unblock(): bool
    {
        if ($this->status !== self::STATUS_BLOCKED) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_DECLINED,
            'blocked_at' => null,
        ]);

        return true;
    }

    /**
     * Check if the user can modify this friendship.
     */
    public function canBeModifiedBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Only the recipient can accept/decline pending requests
        if ($this->status === self::STATUS_PENDING) {
            return $user->id === $this->friend_id;
        }

        // Both users can block or unfriend
        return $user->id === $this->user_id || $user->id === $this->friend_id;
    }

    /**
     * Check if the user can see this friendship.
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

        // Users involved can always see
        if ($user->id === $this->user_id || $user->id === $this->friend_id) {
            return true;
        }

        // Other users can only see accepted friendships if show_in_friends_list is true
        return $this->status === self::STATUS_ACCEPTED && $this->show_in_friends_list;
    }

    /**
     * Get the other user in the friendship.
     */
    public function getOtherUser(User $currentUser): ?User
    {
        if ($currentUser->id === $this->user_id) {
            return $this->friend;
        } elseif ($currentUser->id === $this->friend_id) {
            return $this->user;
        }

        return null;
    }

    /**
     * Update mutual friends count for this friendship.
     */
    public function updateMutualFriendsCount(): void
    {
        if ($this->status !== self::STATUS_ACCEPTED) {
            return;
        }

        $mutualCount = $this->calculateMutualFriendsCount();
        $this->update(['mutual_friends_count' => $mutualCount]);
    }

    /**
     * Calculate mutual friends count between the two users.
     */
    public function calculateMutualFriendsCount(): int
    {
        $user1Friends = $this->user->acceptedFriends()
                                   ->limit(self::MAX_MUTUAL_FRIENDS_CALCULATION)
                                   ->pluck('friend_id')
                                   ->merge(
                                       $this->user->acceptedFriendRequests()
                                                  ->limit(self::MAX_MUTUAL_FRIENDS_CALCULATION)
                                                  ->pluck('user_id')
                                   );

        $user2Friends = $this->friend->acceptedFriends()
                                     ->limit(self::MAX_MUTUAL_FRIENDS_CALCULATION)
                                     ->pluck('friend_id')
                                     ->merge(
                                         $this->friend->acceptedFriendRequests()
                                                      ->limit(self::MAX_MUTUAL_FRIENDS_CALCULATION)
                                                      ->pluck('user_id')
                                     );

        return $user1Friends->intersect($user2Friends)->count();
    }

    /**
     * Get mutual friends for this friendship.
     */
    public function getMutualFriends(): Collection
    {
        if ($this->status !== self::STATUS_ACCEPTED) {
            return collect();
        }

        $user1Friends = $this->user->acceptedFriends()
                                   ->pluck('friend_id')
                                   ->merge(
                                       $this->user->acceptedFriendRequests()
                                                  ->pluck('user_id')
                                   );

        $user2Friends = $this->friend->acceptedFriends()
                                     ->pluck('friend_id')
                                     ->merge(
                                         $this->friend->acceptedFriendRequests()
                                                      ->pluck('user_id')
                                     );

        $mutualFriendIds = $user1Friends->intersect($user2Friends);

        return User::whereIn('id', $mutualFriendIds)->get();
    }

    /**
     * Create notification for friend request.
     */
    protected function createFriendRequestNotification(): void
    {
        try {
            $notificationService = app(\App\Services\NotificationService::class);
            
            if ($this->status === self::STATUS_PENDING) {
                // Create friend request notification
                $notificationService->createFriendRequestNotification(
                    $this->user,
                    $this->friend
                );
            } elseif ($this->status === self::STATUS_ACCEPTED) {
                // Create friend request accepted notification
                $notificationService->createFriendAcceptedNotification(
                    $this->friend,
                    $this->user
                );
            }
        } catch (\Exception $e) {
            Log::warning('Failed to create friend request notification: ' . $e->getMessage());
        }
    }

    /**
     * Create friendship accepted notification.
     */
    protected function createFriendshipAcceptedNotification(): void
    {
        // Implementation would create a notification
        // This could be done through a notification system or job
    }

    /**
     * Scope for friendships with a specific status.
     */
    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for pending friendships.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for accepted friendships.
     */
    public function scopeAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACCEPTED);
    }

    /**
     * Scope for blocked friendships.
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_BLOCKED);
    }

    /**
     * Scope for declined friendships.
     */
    public function scopeDeclined(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DECLINED);
    }

    /**
     * Scope for friendships involving a specific user.
     */
    public function scopeInvolvingUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('user_id', $userId)->orWhere('friend_id', $userId);
        });
    }

    /**
     * Scope for friendships sent by a user.
     */
    public function scopeSentBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for friendships received by a user.
     */
    public function scopeReceivedBy(Builder $query, int $userId): Builder
    {
        return $query->where('friend_id', $userId);
    }

    /**
     * Scope for friendships between two specific users.
     */
    public function scopeBetweenUsers(Builder $query, int $userId1, int $userId2): Builder
    {
        return $query->where(function ($q) use ($userId1, $userId2) {
            $q->where(function ($subQ) use ($userId1, $userId2) {
                $subQ->where('user_id', $userId1)->where('friend_id', $userId2);
            })->orWhere(function ($subQ) use ($userId1, $userId2) {
                $subQ->where('user_id', $userId2)->where('friend_id', $userId1);
            });
        });
    }

    /**
     * Scope for recent friendships.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('accepted_at', '>=', now()->subDays($days));
    }

    /**
     * Scope for old friendships.
     */
    public function scopeOld(Builder $query, int $days = 365): Builder
    {
        return $query->accepted()->where('accepted_at', '<=', now()->subDays($days));
    }

    /**
     * Scope for friendships with high mutual friend count.
     */
    public function scopeHighMutualFriends(Builder $query, int $minCount = 5): Builder
    {
        return $query->where('mutual_friends_count', '>=', $minCount);
    }

    /**
     * Scope for visible friendships.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('show_in_friends_list', true);
    }

    /**
     * Scope for friendships that allow post visibility.
     */
    public function scopeCanSeeEachOthersPosts(Builder $query): Builder
    {
        return $query->where('can_see_posts', true);
    }

    /**
     * Scope for friendships that allow messaging.
     */
    public function scopeCanMessage(Builder $query): Builder
    {
        return $query->where('can_send_messages', true);
    }

    /**
     * Scope for popular users (by friend count).
     */
    public function scopePopularUsers(Builder $query): Builder
    {
        return $query->select('friend_id')
                    ->accepted()
                    ->groupBy('friend_id')
                    ->havingRaw('COUNT(*) > 50')
                    ->orderByRaw('COUNT(*) DESC');
    }

    /**
     * Get friendship statistics for a user.
     */
    public static function getStatsForUser(User $user): array
    {
        $stats = [
            'total_friends' => 0,
            'pending_sent' => 0,
            'pending_received' => 0,
            'blocked_users' => 0,
            'declined_requests' => 0,
            'mutual_friends_avg' => 0,
            'recent_friendships' => 0,
        ];

        $friendships = static::involvingUser($user->id)->get();

        foreach ($friendships as $friendship) {
            switch ($friendship->status) {
                case self::STATUS_ACCEPTED:
                    $stats['total_friends']++;
                    break;
                case self::STATUS_PENDING:
                    if ($friendship->user_id === $user->id) {
                        $stats['pending_sent']++;
                    } else {
                        $stats['pending_received']++;
                    }
                    break;
                case self::STATUS_BLOCKED:
                    $stats['blocked_users']++;
                    break;
                case self::STATUS_DECLINED:
                    $stats['declined_requests']++;
                    break;
            }
        }

        $acceptedFriendships = $friendships->where('status', self::STATUS_ACCEPTED);
        if ($acceptedFriendships->isNotEmpty()) {
            $stats['mutual_friends_avg'] = (int) round($acceptedFriendships->avg('mutual_friends_count'));
        }

        $stats['recent_friendships'] = $acceptedFriendships
            ->where('accepted_at', '>=', now()->subDays(30))
            ->count();

        return $stats;
    }

    /**
     * Find or create friendship between two users.
     */
    public static function findOrCreateBetween(User $user1, User $user2): ?static
    {
        if ($user1->id === $user2->id) {
            return null;
        }

        return static::betweenUsers($user1->id, $user2->id)->first() ??
               static::create([
                   'user_id' => $user1->id,
                   'friend_id' => $user2->id,
                   'status' => self::STATUS_PENDING,
               ]);
    }

    /**
     * Check if two users can be friends.
     */
    public static function canBeFriends(User $user1, User $user2): bool
    {
        if ($user1->id === $user2->id) {
            return false;
        }

        // Check if they're already friends or have a pending request
        $existingFriendship = static::betweenUsers($user1->id, $user2->id)->first();
        if ($existingFriendship && in_array($existingFriendship->status, [self::STATUS_ACCEPTED, self::STATUS_PENDING])) {
            return false;
        }

        // Check if either user has blocked the other
        if ($existingFriendship && $existingFriendship->status === self::STATUS_BLOCKED) {
            return false;
        }

        // Check privacy settings
        if (!$user2->profile->allow_friend_requests) {
            return false;
        }

        return true;
    }
}
