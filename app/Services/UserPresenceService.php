<?php

namespace App\Services;

use App\Models\User;
use App\Events\UserOnlineStatusChanged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserPresenceService
{
    private const ONLINE_THRESHOLD_MINUTES = 5;
    private const CACHE_TTL = 300; // 5 minutes
    private const ACTIVITY_BUFFER_MINUTES = 2;

    /**
     * Mark user as online and update their activity
     */
    public function markUserOnline(User $user): void
    {
        $previousStatus = $this->isUserOnline($user);
        $now = now();

        // Update database
        $user->update([
            'is_online' => true,
            'last_activity_at' => $now,
        ]);

        // Update cache
        $this->updateUserPresenceCache($user->id, true, $now);

        // Broadcast status change if user was offline
        if (!$previousStatus) {
            try {
                broadcast(new UserOnlineStatusChanged($user, true))->toOthers();
                Log::info('User came online', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast user online status', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mark user as offline
     */
    public function markUserOffline(User $user): void
    {
        $previousStatus = $this->isUserOnline($user);

        // Update database
        $user->update([
            'is_online' => false,
            'last_activity_at' => now(),
        ]);

        // Update cache
        $this->updateUserPresenceCache($user->id, false, now());

        // Broadcast status change if user was online
        if ($previousStatus) {
            try {
                broadcast(new UserOnlineStatusChanged($user, false))->toOthers();
                Log::info('User went offline', ['user_id' => $user->id]);
            } catch (\Exception $e) {
                Log::error('Failed to broadcast user offline status', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Update user activity without changing online status
     */
    public function updateUserActivity(User $user): void
    {
        $now = now();
        
        // Update database with throttling (only if last update was more than buffer time ago)
        $shouldUpdateDb = !$user->last_activity_at || 
            $user->last_activity_at->diffInMinutes($now) >= self::ACTIVITY_BUFFER_MINUTES;

        if ($shouldUpdateDb) {
            $user->updateQuietly(['last_activity_at' => $now]);
        }

        // Always update cache for real-time presence
        $this->updateUserPresenceCache($user->id, true, $now);
    }

    /**
     * Check if user is currently online
     */
    public function isUserOnline(User $user): bool
    {
        // First check cache
        $cachedPresence = $this->getUserPresenceFromCache($user->id);
        if ($cachedPresence) {
            $lastActivity = Carbon::parse($cachedPresence['last_activity_at']);
            return $cachedPresence['is_online'] && 
                   $lastActivity->diffInMinutes(now()) <= self::ONLINE_THRESHOLD_MINUTES;
        }

        // Fallback to database
        if (!$user->last_activity_at) {
            return false;
        }

        return $user->is_online && 
               $user->last_activity_at->diffInMinutes(now()) <= self::ONLINE_THRESHOLD_MINUTES;
    }

    /**
     * Get user's last activity timestamp
     */
    public function getLastActivity(User $user): ?Carbon
    {
        // Check cache first
        $cachedPresence = $this->getUserPresenceFromCache($user->id);
        if ($cachedPresence) {
            return Carbon::parse($cachedPresence['last_activity_at']);
        }

        return $user->last_activity_at;
    }

    /**
     * Get online status for multiple users
     */
    public function getBulkUserStatus(array $userIds): array
    {
        $statuses = [];
        $uncachedIds = [];

        // Check cache first
        foreach ($userIds as $userId) {
            $cachedPresence = $this->getUserPresenceFromCache($userId);
            if ($cachedPresence) {
                $lastActivity = Carbon::parse($cachedPresence['last_activity_at']);
                $statuses[$userId] = [
                    'is_online' => $cachedPresence['is_online'] && 
                                  $lastActivity->diffInMinutes(now()) <= self::ONLINE_THRESHOLD_MINUTES,
                    'last_activity_at' => $lastActivity,
                ];
            } else {
                $uncachedIds[] = $userId;
            }
        }

        // Fetch uncached users from database
        if (!empty($uncachedIds)) {
            $users = User::whereIn('id', $uncachedIds)
                         ->select('id', 'is_online', 'last_activity_at')
                         ->get();

            foreach ($users as $user) {
                $isOnline = $user->is_online && 
                           $user->last_activity_at && 
                           $user->last_activity_at->diffInMinutes(now()) <= self::ONLINE_THRESHOLD_MINUTES;

                $statuses[$user->id] = [
                    'is_online' => $isOnline,
                    'last_activity_at' => $user->last_activity_at,
                ];

                // Cache the result
                $this->updateUserPresenceCache(
                    $user->id, 
                    $isOnline, 
                    $user->last_activity_at ?: now()
                );
            }
        }

        return $statuses;
    }

    /**
     * Get list of currently online users
     */
    public function getOnlineUsers(int $limit = 50): array
    {
        $onlineUsers = User::where('is_online', true)
            ->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES))
            ->select('id', 'name', 'username', 'last_activity_at')
            ->with('profile:user_id,avatar_url,show_online_status')
            ->orderBy('last_activity_at', 'desc')
            ->limit($limit)
            ->get();

        // Filter users who have online status visibility enabled
        return $onlineUsers->filter(function ($user) {
            return $user->profile?->show_online_status ?? true;
        })->values()->toArray();
    }

    /**
     * Get count of online users
     */
    public function getOnlineUserCount(): int
    {
        $cacheKey = 'online_users_count';
        
        return Cache::remember($cacheKey, 60, function () {
            return User::where('is_online', true)
                      ->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES))
                      ->whereHas('profile', function ($query) {
                          $query->where('show_online_status', true);
                      })
                      ->count();
        });
    }

    /**
     * Cleanup offline users (run periodically)
     */
    public function cleanupOfflineUsers(): int
    {
        $cutoffTime = now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES);
        
        $updatedCount = User::where('is_online', true)
            ->where('last_activity_at', '<', $cutoffTime)
            ->update(['is_online' => false]);

        if ($updatedCount > 0) {
            Log::info('Cleaned up offline users', ['count' => $updatedCount]);
        }

        return $updatedCount;
    }

    /**
     * Get user activity patterns (for analytics)
     */
    public function getUserActivityPatterns(User $user, int $days = 7): array
    {
        $activities = [];
        $startDate = now()->subDays($days);

        // This would be expanded with actual activity tracking
        // For now, return basic last activity info
        return [
            'user_id' => $user->id,
            'last_activity_at' => $user->last_activity_at,
            'is_currently_online' => $this->isUserOnline($user),
            'days_tracked' => $days,
            // Additional analytics could be added here
        ];
    }

    /**
     * Update user presence in cache
     */
    private function updateUserPresenceCache(int $userId, bool $isOnline, Carbon $lastActivity): void
    {
        $cacheKey = "user_presence_{$userId}";
        $data = [
            'is_online' => $isOnline,
            'last_activity_at' => $lastActivity->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        Cache::put($cacheKey, $data, self::CACHE_TTL);
    }

    /**
     * Get user presence from cache
     */
    private function getUserPresenceFromCache(int $userId): ?array
    {
        $cacheKey = "user_presence_{$userId}";
        return Cache::get($cacheKey);
    }

    /**
     * Clear user presence cache
     */
    public function clearUserPresenceCache(int $userId): void
    {
        Cache::forget("user_presence_{$userId}");
    }

    /**
     * Get friends who are currently online
     */
    public function getOnlineFriends(User $user): array
    {
        $friendIds = $user->friends()->accepted()->pluck('friend_id')->toArray();
        $userFriendIds = $user->friendOf()->accepted()->pluck('user_id')->toArray();
        $allFriendIds = array_merge($friendIds, $userFriendIds);

        if (empty($allFriendIds)) {
            return [];
        }

        $onlineStatuses = $this->getBulkUserStatus($allFriendIds);
        $onlineFriendIds = array_filter($allFriendIds, function ($friendId) use ($onlineStatuses) {
            return $onlineStatuses[$friendId]['is_online'] ?? false;
        });

        if (empty($onlineFriendIds)) {
            return [];
        }

        return User::whereIn('id', $onlineFriendIds)
                   ->select('id', 'name', 'username')
                   ->with('profile:user_id,avatar_url')
                   ->get()
                   ->toArray();
    }

    /**
     * Check if user allows presence visibility
     */
    public function isPresenceVisible(User $user): bool
    {
        return $user->profile?->show_online_status ?? true;
    }

    /**
     * Batch update presence for multiple users (for system operations)
     */
    public function batchUpdatePresence(array $userPresenceData): void
    {
        foreach ($userPresenceData as $data) {
            $this->updateUserPresenceCache(
                $data['user_id'],
                $data['is_online'],
                Carbon::parse($data['last_activity_at'])
            );
        }
    }
} 