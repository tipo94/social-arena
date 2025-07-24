<?php

namespace App\Services;

use App\Models\User;
use App\Models\Friendship;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FriendSuggestionService
{
    /**
     * Maximum suggestions to return per request.
     */
    const MAX_SUGGESTIONS = 100;

    /**
     * Cache duration for suggestion results (in minutes).
     */
    const CACHE_DURATION = 60;

    /**
     * Minimum mutual friends required for high-confidence suggestions.
     */
    const MIN_MUTUAL_FRIENDS = 2;

    /**
     * Maximum degrees of separation to consider.
     */
    const MAX_DEGREES_SEPARATION = 3;

    /**
     * Generate friend suggestions for a user.
     */
    public function getSuggestions(User $user, array $options = []): Collection
    {
        $options = array_merge([
            'count' => 10,
            'algorithm' => 'mutual_connections',
            'include_scores' => false,
            'min_score' => 0.1,
            'exclude_recent_interactions' => true,
            'use_cache' => true,
        ], $options);

        $cacheKey = $this->getCacheKey($user, $options);

        if ($options['use_cache'] && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $suggestions = $this->generateSuggestions($user, $options);

        if ($options['use_cache']) {
            Cache::put($cacheKey, $suggestions, now()->addMinutes(self::CACHE_DURATION));
        }

        return $suggestions;
    }

    /**
     * Generate suggestions using various algorithms.
     */
    protected function generateSuggestions(User $user, array $options): Collection
    {
        $algorithm = $options['algorithm'];
        $count = min($options['count'], self::MAX_SUGGESTIONS);

        // Get excluded user IDs (existing friends, blocked users, etc.)
        $excludedIds = $this->getExcludedUserIds($user, $options);

        switch ($algorithm) {
            case 'mutual_connections':
                return $this->getMutualConnectionSuggestions($user, $excludedIds, $options);
            case 'friends_of_friends':
                return $this->getFriendsOfFriendsSuggestions($user, $excludedIds, $options);
            case 'network_analysis':
                return $this->getNetworkAnalysisSuggestions($user, $excludedIds, $options);
            case 'hybrid':
                return $this->getHybridSuggestions($user, $excludedIds, $options);
            default:
                return $this->getMutualConnectionSuggestions($user, $excludedIds, $options);
        }
    }

    /**
     * Get suggestions based on mutual connections with scoring.
     */
    protected function getMutualConnectionSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        $userFriends = $this->getUserFriendIds($user);
        
        if ($userFriends->isEmpty()) {
            return collect();
        }

        // Find users who are friends with the user's friends
        $suggestions = DB::table('friendships as f1')
            ->select([
                'f1.user_id as suggested_user_id',
                DB::raw('COUNT(*) as mutual_friends_count'),
                DB::raw('GROUP_CONCAT(DISTINCT f1.friend_id) as mutual_friend_ids')
            ])
            ->join('friendships as f2', function ($join) use ($userFriends) {
                $join->on('f1.friend_id', '=', 'f2.user_id')
                     ->whereIn('f2.friend_id', $userFriends)
                     ->where('f2.status', 'accepted');
            })
            ->where('f1.status', 'accepted')
            ->whereNotIn('f1.user_id', $excludedIds)
            ->groupBy('f1.user_id')
            ->havingRaw('COUNT(*) >= ?', [$options['min_mutual_friends'] ?? 1])
            ->orderByRaw('COUNT(*) desc')
            ->limit($options['count'] * 2) // Get extra for filtering
            ->get();

        // Also check the reverse direction (friend_id -> user_id)
        $reverseSuggestions = DB::table('friendships as f1')
            ->select([
                'f1.friend_id as suggested_user_id',
                DB::raw('COUNT(*) as mutual_friends_count'),
                DB::raw('GROUP_CONCAT(DISTINCT f1.user_id) as mutual_friend_ids')
            ])
            ->join('friendships as f2', function ($join) use ($userFriends) {
                $join->on('f1.user_id', '=', 'f2.user_id')
                     ->whereIn('f2.friend_id', $userFriends)
                     ->where('f2.status', 'accepted');
            })
            ->where('f1.status', 'accepted')
            ->whereNotIn('f1.friend_id', $excludedIds)
            ->groupBy('f1.friend_id')
            ->havingRaw('COUNT(*) >= ?', [$options['min_mutual_friends'] ?? 1])
            ->orderByRaw('COUNT(*) desc')
            ->limit($options['count'] * 2)
            ->get();

        // Merge and deduplicate suggestions
        $mergedSuggestions = $suggestions->concat($reverseSuggestions)
            ->groupBy('suggested_user_id')
            ->map(function ($group) {
                $first = $group->first();
                $totalMutual = $group->sum('mutual_friends_count');
                
                return (object) [
                    'suggested_user_id' => $first->suggested_user_id,
                    'mutual_friends_count' => $totalMutual,
                    'mutual_friend_ids' => $group->pluck('mutual_friend_ids')->implode(','),
                ];
            })
            ->sortByDesc('mutual_friends_count');

        return $this->enrichSuggestions($mergedSuggestions, $user, $options);
    }

    /**
     * Get friends-of-friends suggestions with path analysis.
     */
    protected function getFriendsOfFriendsSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        $userFriends = $this->getUserFriendIds($user);
        
        if ($userFriends->isEmpty()) {
            return collect();
        }

        // Multi-level friend discovery
        $suggestions = collect();
        
        for ($degree = 2; $degree <= self::MAX_DEGREES_SEPARATION; $degree++) {
            $degreeSuggestions = $this->getFriendsAtDegree($user, $degree, $excludedIds, $options);
            $suggestions = $suggestions->concat($degreeSuggestions);
            
            if ($suggestions->count() >= $options['count']) {
                break;
            }
        }

        return $suggestions->take($options['count']);
    }

    /**
     * Get friends at a specific degree of separation.
     */
    protected function getFriendsAtDegree(User $user, int $degree, array $excludedIds, array $options): Collection
    {
        // This is a simplified version - in production, you'd want to use graph algorithms
        $currentLevel = collect([$user->id]);
        
        for ($i = 1; $i < $degree; $i++) {
            $nextLevel = DB::table('friendships')
                ->whereIn('user_id', $currentLevel)
                ->where('status', 'accepted')
                ->pluck('friend_id')
                ->merge(
                    DB::table('friendships')
                        ->whereIn('friend_id', $currentLevel)
                        ->where('status', 'accepted')
                        ->pluck('user_id')
                )
                ->unique()
                ->values();
                
            $currentLevel = $nextLevel;
        }

                 // Get final level suggestions with scoring
         if ($currentLevel->isEmpty()) {
             return collect();
         }

         $suggestions = User::select([
                 'id as suggested_user_id',
                 DB::raw("'{$degree}' as degree_separation"),
                 DB::raw('1.0 / ' . $degree . ' as proximity_score')
             ])
             ->whereIn('id', $currentLevel)
             ->whereNotIn('id', $excludedIds)
             ->whereHas('profile', function ($query) {
                 $query->where('allow_friend_requests', true)
                       ->where('is_private_profile', false);
             })
             ->limit($options['count'])
             ->get()
             ->map(function ($suggestion) use ($user, $options) {
                 return $this->enrichSingleSuggestion($suggestion, $user, $options);
             });

         return $suggestions->filter();
    }

    /**
     * Get suggestions using network analysis algorithms.
     */
    protected function getNetworkAnalysisSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        // Implement advanced network analysis algorithms
        $suggestions = collect();

        // 1. Common neighbor analysis
        $commonNeighbors = $this->getCommonNeighborAnalysis($user, $excludedIds, $options);
        
        // 2. Jaccard coefficient calculation
        $jaccardSuggestions = $this->getJaccardCoefficientSuggestions($user, $excludedIds, $options);
        
        // 3. Preferential attachment analysis
        $preferentialSuggestions = $this->getPreferentialAttachmentSuggestions($user, $excludedIds, $options);

        // Combine all suggestions with weighted scoring
        $suggestions = $commonNeighbors
            ->concat($jaccardSuggestions)
            ->concat($preferentialSuggestions)
            ->groupBy('suggested_user_id')
            ->map(function ($group) {
                $combined = $group->first();
                $combined->composite_score = $group->avg('score');
                $combined->algorithm_count = $group->count();
                return $combined;
            })
            ->sortByDesc('composite_score');

        return $suggestions->take($options['count']);
    }

    /**
     * Get hybrid suggestions combining multiple algorithms.
     */
    protected function getHybridSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        $hybridOptions = array_merge($options, ['count' => $options['count'] * 2]);
        
        // Get suggestions from different algorithms
        $mutualSuggestions = $this->getMutualConnectionSuggestions($user, $excludedIds, $hybridOptions);
        $networkSuggestions = $this->getNetworkAnalysisSuggestions($user, $excludedIds, $hybridOptions);
        $friendsOfFriends = $this->getFriendsOfFriendsSuggestions($user, $excludedIds, $hybridOptions);

        // Combine with weighted scoring
        $allSuggestions = collect()
            ->concat($mutualSuggestions->map(fn($s) => $this->addAlgorithmWeight($s, 'mutual', 0.5)))
            ->concat($networkSuggestions->map(fn($s) => $this->addAlgorithmWeight($s, 'network', 0.3)))
            ->concat($friendsOfFriends->map(fn($s) => $this->addAlgorithmWeight($s, 'friends_of_friends', 0.2)));

        // Aggregate scores and rank
        $rankedSuggestions = $allSuggestions
            ->groupBy('suggested_user_id')
            ->map(function ($group) {
                $suggestion = $group->first();
                $suggestion->final_score = $group->sum('weighted_score');
                $suggestion->algorithm_coverage = $group->pluck('algorithm')->unique()->count();
                return $suggestion;
            })
            ->sortByDesc('final_score')
            ->take($options['count']);

        return $rankedSuggestions;
    }

    /**
     * Analyze common neighbors between users.
     */
    protected function getCommonNeighborAnalysis(User $user, array $excludedIds, array $options): Collection
    {
        $userFriends = $this->getUserFriendIds($user);
        
        return DB::table('friendships as f1')
            ->select([
                'f1.user_id as suggested_user_id',
                DB::raw('COUNT(*) as common_neighbors'),
                DB::raw('COUNT(*) / (SELECT COUNT(*) FROM friendships WHERE user_id = f1.user_id AND status = "accepted") as score')
            ])
            ->join('friendships as f2', 'f1.friend_id', '=', 'f2.friend_id')
            ->where('f1.status', 'accepted')
            ->where('f2.status', 'accepted')
            ->whereIn('f2.user_id', $userFriends)
            ->whereNotIn('f1.user_id', $excludedIds)
            ->groupBy('f1.user_id')
            ->having('common_neighbors', '>=', 2)
            ->orderBy('score', 'desc')
            ->limit($options['count'])
            ->get();
    }

    /**
     * Calculate Jaccard coefficient for user similarity.
     */
    protected function getJaccardCoefficientSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        $userFriends = $this->getUserFriendIds($user);
        $userFriendsCount = $userFriends->count();
        
        if ($userFriendsCount === 0) {
            return collect();
        }

        return DB::table(DB::raw('(
            SELECT 
                f1.user_id as suggested_user_id,
                COUNT(DISTINCT f1.friend_id) as target_friends_count,
                COUNT(DISTINCT CASE WHEN f2.user_id IS NOT NULL THEN f1.friend_id END) as common_friends,
                COUNT(DISTINCT f1.friend_id) + ' . $userFriendsCount . ' - COUNT(DISTINCT CASE WHEN f2.user_id IS NOT NULL THEN f1.friend_id END) as union_friends
            FROM friendships f1
            LEFT JOIN friendships f2 ON f1.friend_id = f2.friend_id AND f2.user_id = ' . $user->id . ' AND f2.status = "accepted"
            WHERE f1.status = "accepted"
            AND f1.user_id NOT IN (' . implode(',', $excludedIds) . ')
            GROUP BY f1.user_id
            HAVING common_friends > 0
        ) as jaccard_data'))
            ->select([
                'suggested_user_id',
                'common_friends',
                DB::raw('common_friends / union_friends as jaccard_coefficient'),
                DB::raw('common_friends / union_friends as score')
            ])
            ->orderBy('jaccard_coefficient', 'desc')
            ->limit($options['count'])
            ->get();
    }

    /**
     * Analyze preferential attachment patterns.
     */
    protected function getPreferentialAttachmentSuggestions(User $user, array $excludedIds, array $options): Collection
    {
        // Users with many friends are more likely to be suggested (preferential attachment)
        return DB::table('users')
            ->select([
                'users.id as suggested_user_id',
                DB::raw('COUNT(f.id) as friend_count'),
                DB::raw('LOG(COUNT(f.id) + 1) as attachment_score'),
                DB::raw('LOG(COUNT(f.id) + 1) as score')
            ])
            ->leftJoin('friendships as f', function ($join) {
                $join->on('users.id', '=', 'f.user_id')
                     ->orOn('users.id', '=', 'f.friend_id');
            })
            ->where('f.status', 'accepted')
            ->whereNotIn('users.id', $excludedIds)
            ->whereHas('profile', function ($query) {
                $query->where('allow_friend_requests', true)
                      ->where('is_private_profile', false);
            })
            ->groupBy('users.id')
            ->having('friend_count', '>', 10) // Only suggest popular users
            ->orderBy('attachment_score', 'desc')
            ->limit($options['count'])
            ->get();
    }

    /**
     * Enrich suggestions with user data and additional scoring.
     */
    protected function enrichSuggestions(Collection $suggestions, User $user, array $options): Collection
    {
        $userIds = $suggestions->pluck('suggested_user_id')->unique();
        
        $users = User::with('profile')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        return $suggestions->map(function ($suggestion) use ($users, $user, $options) {
            return $this->enrichSingleSuggestion($suggestion, $user, $options, $users);
        })->filter()->values();
    }

    /**
     * Enrich a single suggestion with user data and scoring.
     */
    protected function enrichSingleSuggestion($suggestion, User $user, array $options, Collection $users = null): ?object
    {
        $suggestedUser = $users ? $users->get($suggestion->suggested_user_id) : User::with('profile')->find($suggestion->suggested_user_id);
        
        if (!$suggestedUser || !$suggestedUser->profile) {
            return null;
        }

        // Calculate comprehensive scoring
        $scores = $this->calculateSuggestionScores($suggestion, $user, $suggestedUser);
        
        // Filter by minimum score if specified
        if (isset($options['min_score']) && $scores['total_score'] < $options['min_score']) {
            return null;
        }

        $enriched = (object) [
            'user_id' => $suggestedUser->id,
            'name' => $suggestedUser->name,
            'username' => $suggestedUser->username,
            'avatar_url' => $suggestedUser->profile->avatar_url,
            'is_verified' => $suggestedUser->profile->is_verified ?? false,
            'mutual_friends_count' => $suggestion->mutual_friends_count ?? 0,
            'suggestion_reason' => $this->getSuggestionReason($suggestion),
            'confidence_score' => $scores['total_score'],
            'created_at' => now()->toISOString(),
        ];

        // Include detailed scores if requested
        if ($options['include_scores']) {
            $enriched->scores = $scores;
            $enriched->mutual_friend_ids = isset($suggestion->mutual_friend_ids) 
                ? explode(',', $suggestion->mutual_friend_ids) 
                : [];
        }

        return $enriched;
    }

    /**
     * Calculate comprehensive suggestion scores.
     */
    protected function calculateSuggestionScores($suggestion, User $user, User $suggestedUser): array
    {
        $scores = [
            'mutual_connections' => 0,
            'profile_completeness' => 0,
            'activity_level' => 0,
            'interest_similarity' => 0,
            'location_proximity' => 0,
            'recency_boost' => 0,
        ];

        // Mutual connections score (0-1)
        $mutualCount = $suggestion->mutual_friends_count ?? 0;
        $scores['mutual_connections'] = min($mutualCount / 10, 1.0);

        // Profile completeness score (0-1)
        $completeness = 0;
        if ($suggestedUser->profile->bio) $completeness += 0.2;
        if ($suggestedUser->profile->avatar_url) $completeness += 0.2;
        if ($suggestedUser->profile->location) $completeness += 0.2;
        if ($suggestedUser->profile->favorite_genres) $completeness += 0.2;
        if ($suggestedUser->profile->reading_goal) $completeness += 0.2;
        $scores['profile_completeness'] = $completeness;

        // Activity level score (0-1)
        $lastActivity = $suggestedUser->last_activity_at ?? $suggestedUser->created_at;
        $daysSinceActivity = now()->diffInDays($lastActivity);
        $scores['activity_level'] = max(0, 1 - ($daysSinceActivity / 30));

        // Interest similarity score (0-1)
        if ($user->profile->favorite_genres && $suggestedUser->profile->favorite_genres) {
            $userGenres = collect($user->profile->favorite_genres);
            $suggestedGenres = collect($suggestedUser->profile->favorite_genres);
            $intersection = $userGenres->intersect($suggestedGenres)->count();
            $union = $userGenres->merge($suggestedGenres)->unique()->count();
            $scores['interest_similarity'] = $union > 0 ? $intersection / $union : 0;
        }

        // Location proximity score (0-1)
        if ($user->profile->location && $suggestedUser->profile->location) {
            $userLocation = strtolower($user->profile->location);
            $suggestedLocation = strtolower($suggestedUser->profile->location);
            
            if ($userLocation === $suggestedLocation) {
                $scores['location_proximity'] = 1.0;
            } elseif (str_contains($userLocation, $suggestedLocation) || str_contains($suggestedLocation, $userLocation)) {
                $scores['location_proximity'] = 0.7;
            }
        }

        // Recency boost for recently joined users (0-0.1)
        $accountAge = now()->diffInDays($suggestedUser->created_at);
        $scores['recency_boost'] = $accountAge <= 30 ? 0.1 : 0;

        // Calculate weighted total score
        $weights = [
            'mutual_connections' => 0.4,
            'profile_completeness' => 0.15,
            'activity_level' => 0.15,
            'interest_similarity' => 0.15,
            'location_proximity' => 0.1,
            'recency_boost' => 0.05,
        ];

        $totalScore = 0;
        foreach ($scores as $type => $score) {
            $totalScore += $score * $weights[$type];
        }

        $scores['total_score'] = $totalScore;
        return $scores;
    }

    /**
     * Get suggestion reason text.
     */
    protected function getSuggestionReason($suggestion): string
    {
        $mutualCount = $suggestion->mutual_friends_count ?? 0;
        
        if ($mutualCount >= 5) {
            return "You have {$mutualCount} mutual friends";
        } elseif ($mutualCount >= 2) {
            return "You have {$mutualCount} mutual friends";
        } elseif ($mutualCount === 1) {
            return "You have 1 mutual friend";
        } elseif (isset($suggestion->degree_separation)) {
            return "Friend of a friend";
        } elseif (isset($suggestion->common_neighbors)) {
            return "Active in your network";
        } else {
            return "Suggested for you";
        }
    }

    /**
     * Get user's friend IDs.
     */
    protected function getUserFriendIds(User $user): Collection
    {
        return DB::table('friendships')
            ->where('status', 'accepted')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->get()
            ->flatMap(function ($friendship) use ($user) {
                return $friendship->user_id === $user->id 
                    ? [$friendship->friend_id] 
                    : [$friendship->user_id];
            })
            ->unique()
            ->values();
    }

    /**
     * Get excluded user IDs (existing friends, blocked users, etc.).
     */
    protected function getExcludedUserIds(User $user, array $options): array
    {
        $excluded = collect([$user->id]);

        // Add existing friends
        $excluded = $excluded->concat($this->getUserFriendIds($user));

        // Add users with existing friendship records
        $existingRelationships = DB::table('friendships')
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('friend_id', $user->id);
            })
            ->get()
            ->flatMap(function ($friendship) use ($user) {
                return $friendship->user_id === $user->id 
                    ? [$friendship->friend_id] 
                    : [$friendship->user_id];
            });

        $excluded = $excluded->concat($existingRelationships);

        // Add users who don't allow friend requests
        $nonRequestableUsers = User::whereHas('profile', function ($query) {
            $query->where('allow_friend_requests', false)
                  ->orWhere('is_private_profile', true);
        })->pluck('id');

        $excluded = $excluded->concat($nonRequestableUsers);

        // Exclude users with recent interactions if specified
        if (isset($options['exclude_recent_interactions']) && $options['exclude_recent_interactions']) {
            // This could include recent message exchanges, post interactions, etc.
            // For now, we'll keep it simple
        }

        return $excluded->unique()->values()->toArray();
    }

    /**
     * Add algorithm weight to suggestion.
     */
    protected function addAlgorithmWeight($suggestion, string $algorithm, float $weight): object
    {
        $suggestion->algorithm = $algorithm;
        $suggestion->weight = $weight;
        $suggestion->weighted_score = ($suggestion->score ?? $suggestion->confidence_score ?? 0.5) * $weight;
        return $suggestion;
    }

    /**
     * Generate cache key for suggestions.
     */
    protected function getCacheKey(User $user, array $options): string
    {
        $key = "friend_suggestions:{$user->id}:" . md5(json_encode($options));
        return $key;
    }

    /**
     * Clear suggestion cache for a user.
     */
    public function clearUserCache(User $user): void
    {
        $pattern = "friend_suggestions:{$user->id}:*";
        // In production, you'd want to use a more sophisticated cache clearing mechanism
        Cache::forget($pattern);
    }

    /**
     * Get suggestion analytics for a user.
     */
    public function getSuggestionAnalytics(User $user): array
    {
        $userFriends = $this->getUserFriendIds($user);
        
        return [
            'total_friends' => $userFriends->count(),
            'suggestion_pool_size' => $this->getSuggestionPoolSize($user),
            'network_density' => $this->calculateNetworkDensity($user),
            'avg_mutual_friends' => $this->getAverageMutualFriends($user),
            'last_updated' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the size of potential suggestion pool.
     */
    protected function getSuggestionPoolSize(User $user): int
    {
        $excludedIds = $this->getExcludedUserIds($user, []);
        
        return User::whereNotIn('id', $excludedIds)
            ->whereHas('profile', function ($query) {
                $query->where('allow_friend_requests', true)
                      ->where('is_private_profile', false);
            })
            ->count();
    }

    /**
     * Calculate network density for the user.
     */
    protected function calculateNetworkDensity(User $user): float
    {
        $friends = $this->getUserFriendIds($user);
        $friendCount = $friends->count();
        
        if ($friendCount < 2) {
            return 0.0;
        }

        // Count connections between user's friends
        $internalConnections = DB::table('friendships')
            ->where('status', 'accepted')
            ->where(function ($query) use ($friends) {
                $query->whereIn('user_id', $friends)
                      ->whereIn('friend_id', $friends);
            })
            ->count();

        $maxPossibleConnections = $friendCount * ($friendCount - 1) / 2;
        
        return $maxPossibleConnections > 0 ? $internalConnections / $maxPossibleConnections : 0.0;
    }

    /**
     * Get average mutual friends count for user's network.
     */
    protected function getAverageMutualFriends(User $user): float
    {
        $friends = $this->getUserFriendIds($user);
        
        if ($friends->count() < 2) {
            return 0.0;
        }

        $friendships = Friendship::whereIn('user_id', $friends)
            ->orWhereIn('friend_id', $friends)
            ->where('status', 'accepted')
            ->get();

        return $friendships->avg('mutual_friends_count') ?? 0.0;
    }
} 