<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FeedService;
use App\Http\Resources\PostResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class FeedController extends Controller
{
    public function __construct(
        protected FeedService $feedService
    ) {}

    /**
     * Get user's main feed with advanced options.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|string|in:chronological,algorithmic,following,trending,discover,bookmarks',
            'per_page' => 'sometimes|integer|min:5|max:50',
            'cursor' => 'sometimes|string',
            'period' => 'sometimes|string|in:today,week,month,year,all',
            'bypass_cache' => 'sometimes|boolean',
            
            // Advanced filtering options
            'filters' => 'sometimes|array',
            'filters.content_types' => 'sometimes|array',
            'filters.content_types.*' => 'string|in:text,image,video,link,book_review,poll',
            'filters.has_media' => 'sometimes|boolean',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date|after:filters.date_from',
            'filters.min_engagement' => 'sometimes|integer|min:0',
            'filters.hashtags' => 'sometimes|array',
            'filters.hashtags.*' => 'string|max:50',
            'filters.exclude_authors' => 'sometimes|array',
            'filters.exclude_authors.*' => 'integer|exists:users,id',
        ]);

        try {
            $user = Auth::user();
            
            $options = [
                'type' => $request->input('type', 'chronological'),
                'per_page' => $request->input('per_page', 15),
                'cursor' => $request->input('cursor'),
                'period' => $request->input('period'),
                'bypass_cache' => $request->input('bypass_cache', false),
                'filters' => $request->input('filters', []),
            ];

            $feedResult = $this->feedService->generateFeed($user, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'posts' => PostResource::collection($feedResult['posts']),
                    'pagination' => $feedResult['pagination'],
                    'feed_info' => [
                        'type' => $options['type'],
                        'applied_filters' => array_keys($options['filters']),
                        'generated_at' => now()->toISOString(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate feed',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get chronological feed (legacy compatibility).
     */
    public function chronological(Request $request): JsonResponse
    {
        $request->merge(['type' => 'chronological']);
        return $this->index($request);
    }

    /**
     * Get algorithmic feed (personalized).
     */
    public function algorithmic(Request $request): JsonResponse
    {
        $request->merge(['type' => 'algorithmic']);
        return $this->index($request);
    }

    /**
     * Get following feed (posts from friends only).
     */
    public function following(Request $request): JsonResponse
    {
        $request->merge(['type' => 'following']);
        return $this->index($request);
    }

    /**
     * Get trending feed (popular posts).
     */
    public function trending(Request $request): JsonResponse
    {
        $request->validate([
            'hours' => 'sometimes|integer|min:1|max:168', // 1 hour to 1 week
        ]);

        $options = ['type' => 'trending'];
        if ($request->has('hours')) {
            $options['trending_hours'] = $request->input('hours');
        }

        $request->merge($options);
        return $this->index($request);
    }

    /**
     * Get discover feed (new content recommendations).
     */
    public function discover(Request $request): JsonResponse
    {
        $request->validate([
            'sample_size' => 'sometimes|integer|min:100|max:2000',
            'include_interests' => 'sometimes|array',
            'include_interests.*' => 'string|max:50',
            'exclude_seen' => 'sometimes|boolean',
        ]);

        $options = [
            'type' => 'discover',
            'sample_size' => $request->input('sample_size'),
            'include_interests' => $request->input('include_interests'),
            'exclude_seen' => $request->input('exclude_seen', true),
        ];

        $request->merge($options);
        return $this->index($request);
    }

    /**
     * Get bookmarks feed (saved posts).
     */
    public function bookmarks(Request $request): JsonResponse
    {
        $request->merge(['type' => 'bookmarks']);
        return $this->index($request);
    }

    /**
     * Get feed statistics and analytics.
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'feed_type' => 'sometimes|string|in:chronological,algorithmic,following,trending,discover',
            'period' => 'sometimes|string|in:today,week,month,all',
        ]);

        try {
            $user = Auth::user();
            $feedType = $request->input('feed_type', 'chronological');
            
            $stats = $this->feedService->getFeedStats($user, $feedType);
            
            // Add additional analytics
            $analytics = [
                'user_engagement' => $this->getUserEngagementStats($user),
                'feed_performance' => $this->getFeedPerformanceStats($user, $feedType),
                'content_preferences' => $this->getContentPreferences($user),
            ];

            return response()->json([
                'success' => true,
                'data' => array_merge($stats, $analytics),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feed statistics',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get available feed types for user.
     */
    public function types(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $feedTypes = $this->feedService->getAvailableFeedTypes($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'feed_types' => $feedTypes,
                    'default_type' => 'chronological',
                    'user_preferences' => [
                        'preferred_type' => $user->profile->preferred_feed_type ?? 'chronological',
                        'show_algorithmic_feed' => $user->profile->show_algorithmic_feed ?? true,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feed types',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update user's feed preferences.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $request->validate([
            'preferred_feed_type' => 'required|string|in:chronological,algorithmic,following,trending',
            'show_algorithmic_feed' => 'sometimes|boolean',
            'auto_refresh_interval' => 'sometimes|integer|min:30|max:3600', // 30 seconds to 1 hour
            'default_filters' => 'sometimes|array',
            'content_type_preferences' => 'sometimes|array',
            'content_type_preferences.*' => 'string|in:text,image,video,link,book_review,poll',
        ]);

        try {
            $user = Auth::user();
            
            $updateData = [
                'preferred_feed_type' => $request->input('preferred_feed_type'),
            ];

            if ($request->has('show_algorithmic_feed')) {
                $updateData['show_algorithmic_feed'] = $request->boolean('show_algorithmic_feed');
            }

            if ($request->has('auto_refresh_interval')) {
                $updateData['auto_refresh_interval'] = $request->input('auto_refresh_interval');
            }

            if ($request->has('default_filters')) {
                $updateData['default_feed_filters'] = $request->input('default_filters');
            }

            if ($request->has('content_type_preferences')) {
                $updateData['content_type_preferences'] = $request->input('content_type_preferences');
            }

            $user->profile->update($updateData);

            // Invalidate feed cache to apply new preferences
            $this->feedService->invalidateFeedCache($user);

            return response()->json([
                'success' => true,
                'message' => 'Feed preferences updated successfully',
                'data' => [
                    'updated_preferences' => $updateData,
                    'cache_invalidated' => true,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update feed preferences',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Refresh feed cache.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'feed_types' => 'sometimes|array',
            'feed_types.*' => 'string|in:chronological,algorithmic,following,trending,discover',
        ]);

        try {
            $user = Auth::user();
            
            // Invalidate existing cache
            $this->feedService->invalidateFeedCache($user);
            
            // Warm up specified feed types or all
            $feedTypes = $request->input('feed_types', ['chronological', 'algorithmic', 'following']);
            
            foreach ($feedTypes as $feedType) {
                $this->feedService->generateFeed($user, [
                    'type' => $feedType,
                    'bypass_cache' => true,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Feed cache refreshed successfully',
                'data' => [
                    'refreshed_types' => $feedTypes,
                    'refreshed_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh feed cache',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get feed health and performance metrics.
     */
    public function health(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $health = [
                'cache_status' => $this->checkCacheHealth(),
                'feed_generation_time' => $this->measureFeedGenerationTime($user),
                'available_content_count' => $this->getAvailableContentCount($user),
                'last_cache_refresh' => $this->getLastCacheRefresh($user),
                'system_performance' => [
                    'avg_response_time_ms' => Cache::get('feed:avg_response_time', 0),
                    'cache_hit_rate' => Cache::get('feed:cache_hit_rate', 0),
                    'active_users' => Cache::get('feed:active_users_count', 0),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $health,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get feed health metrics',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Mark posts as seen for algorithmic improvements.
     */
    public function markSeen(Request $request): JsonResponse
    {
        $request->validate([
            'post_ids' => 'required|array|min:1|max:100',
            'post_ids.*' => 'integer|exists:posts,id',
            'interaction_type' => 'sometimes|string|in:viewed,skipped,clicked,engaged',
            'viewing_duration' => 'sometimes|integer|min:0', // seconds
        ]);

        try {
            $user = Auth::user();
            $postIds = $request->input('post_ids');
            $interactionType = $request->input('interaction_type', 'viewed');
            $viewingDuration = $request->input('viewing_duration');

            // Store viewing data for feed algorithm improvement
            $viewingData = [
                'user_id' => $user->id,
                'post_ids' => $postIds,
                'interaction_type' => $interactionType,
                'viewing_duration' => $viewingDuration,
                'timestamp' => now()->toISOString(),
                'user_agent' => $request->userAgent(),
            ];

            // In a full implementation, this would be stored in a dedicated table
            // For now, we'll just cache it for algorithm improvements
            $cacheKey = "feed_interactions:{$user->id}:" . now()->format('Y-m-d-H');
            $existingData = Cache::get($cacheKey, []);
            $existingData[] = $viewingData;
            Cache::put($cacheKey, $existingData, 86400); // 24 hours

            return response()->json([
                'success' => true,
                'message' => 'Post interactions recorded successfully',
                'data' => [
                    'recorded_posts' => count($postIds),
                    'interaction_type' => $interactionType,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to record post interactions',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get personalized content recommendations.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $request->validate([
            'count' => 'sometimes|integer|min:1|max:20',
            'type' => 'sometimes|string|in:users,hashtags,content_types',
        ]);

        try {
            $user = Auth::user();
            $count = $request->input('count', 10);
            $type = $request->input('type', 'users');

            $recommendations = match ($type) {
                'users' => $this->getUserRecommendations($user, $count),
                'hashtags' => $this->getHashtagRecommendations($user, $count),
                'content_types' => $this->getContentTypeRecommendations($user, $count),
                default => [],
            };

            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendations,
                    'type' => $type,
                    'count' => count($recommendations),
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get recommendations',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get user engagement statistics.
     */
    protected function getUserEngagementStats($user): array
    {
        $cacheKey = "user_engagement_stats:{$user->id}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user) {
            return [
                'posts_created_last_week' => $user->posts()->where('created_at', '>=', now()->subWeek())->count(),
                'average_likes_per_post' => $user->posts()->avg('likes_count') ?? 0,
                'average_comments_per_post' => $user->posts()->avg('comments_count') ?? 0,
                'most_active_hour' => $this->getUserMostActiveHour($user),
                'engagement_trend' => $this->getEngagementTrend($user),
            ];
        });
    }

    /**
     * Get feed performance statistics.
     */
    protected function getFeedPerformanceStats($user, string $feedType): array
    {
        return [
            'cache_hit_rate' => Cache::get("feed_cache_hit_rate:{$user->id}:{$feedType}", 0),
            'average_load_time' => Cache::get("feed_load_time:{$user->id}:{$feedType}", 0),
            'posts_per_day' => $this->getAveragePostsPerDay($user, $feedType),
            'content_freshness' => $this->getContentFreshness($user, $feedType),
        ];
    }

    /**
     * Get user content preferences.
     */
    protected function getContentPreferences($user): array
    {
        return [
            'preferred_content_types' => $user->profile->content_type_preferences ?? [],
            'reading_time_preference' => $this->getReadingTimePreference($user),
            'media_preference' => $this->getMediaPreference($user),
            'interaction_patterns' => $this->getInteractionPatterns($user),
        ];
    }

    /**
     * Check cache health status.
     */
    protected function checkCacheHealth(): array
    {
        try {
            Cache::put('health_check', 'ok', 1);
            $retrieved = Cache::get('health_check');
            
            return [
                'status' => $retrieved === 'ok' ? 'healthy' : 'degraded',
                'redis_connected' => true,
                'last_check' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'redis_connected' => false,
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString(),
            ];
        }
    }

    /**
     * Measure feed generation time.
     */
    protected function measureFeedGenerationTime($user): array
    {
        $start = microtime(true);
        
        try {
            $this->feedService->generateFeed($user, [
                'type' => 'chronological',
                'per_page' => 10,
                'bypass_cache' => true,
            ]);
            
            $time = (microtime(true) - $start) * 1000; // Convert to milliseconds
            
            return [
                'generation_time_ms' => round($time, 2),
                'status' => $time < 500 ? 'fast' : ($time < 1000 ? 'moderate' : 'slow'),
            ];
        } catch (\Exception $e) {
            return [
                'generation_time_ms' => null,
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get available content count for user.
     */
    protected function getAvailableContentCount($user): array
    {
        $friendIds = $user->friends()->pluck('id')->toArray();
        
        return [
            'total_posts' => \App\Models\Post::published()->count(),
            'friend_posts' => \App\Models\Post::published()->whereIn('user_id', $friendIds)->count(),
            'public_posts' => \App\Models\Post::published()->where('visibility', 'public')->count(),
            'user_posts' => $user->posts()->published()->count(),
        ];
    }

    /**
     * Get last cache refresh timestamp.
     */
    protected function getLastCacheRefresh($user): ?string
    {
        return Cache::get("last_cache_refresh:{$user->id}");
    }

    /**
     * Get user recommendations.
     */
    protected function getUserRecommendations($user, int $count): array
    {
        // Implementation would analyze user's network and interests
        return [];
    }

    /**
     * Get hashtag recommendations.
     */
    protected function getHashtagRecommendations($user, int $count): array
    {
        // Implementation would analyze trending hashtags and user interests
        return [];
    }

    /**
     * Get content type recommendations.
     */
    protected function getContentTypeRecommendations($user, int $count): array
    {
        // Implementation would analyze user's interaction patterns
        return [];
    }

    /**
     * Get user's most active hour.
     */
    protected function getUserMostActiveHour($user): int
    {
        return $user->posts()
                   ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                   ->groupBy('hour')
                   ->orderByDesc('count')
                   ->value('hour') ?? 12;
    }

    /**
     * Get engagement trend.
     */
    protected function getEngagementTrend($user): string
    {
        // Implementation would compare recent engagement to historical averages
        return 'stable';
    }

    /**
     * Get average posts per day for feed type.
     */
    protected function getAveragePostsPerDay($user, string $feedType): float
    {
        // Implementation would calculate based on feed type and user's network
        return 5.2;
    }

    /**
     * Get content freshness score.
     */
    protected function getContentFreshness($user, string $feedType): float
    {
        // Implementation would calculate how recent the content is
        return 0.85;
    }

    /**
     * Get reading time preference.
     */
    protected function getReadingTimePreference($user): string
    {
        // Implementation would analyze user's typical reading patterns
        return 'medium';
    }

    /**
     * Get media preference.
     */
    protected function getMediaPreference($user): array
    {
        // Implementation would analyze user's media interaction patterns
        return ['text' => 0.4, 'image' => 0.35, 'video' => 0.25];
    }

    /**
     * Get interaction patterns.
     */
    protected function getInteractionPatterns($user): array
    {
        // Implementation would analyze how user typically interacts with content
        return [
            'likes_vs_comments_ratio' => 3.2,
            'shares_per_week' => 2.1,
            'time_spent_reading' => 'medium',
        ];
    }
} 