<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\Friendship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class FeedService
{
    /**
     * Feed types and their configurations.
     */
    const FEED_TYPES = [
        'chronological' => [
            'name' => 'Chronological',
            'description' => 'Latest posts first',
            'cache_ttl' => 300, // 5 minutes
        ],
        'algorithmic' => [
            'name' => 'For You',
            'description' => 'Personalized based on your interests',
            'cache_ttl' => 600, // 10 minutes
        ],
        'following' => [
            'name' => 'Following',
            'description' => 'Posts from people you follow',
            'cache_ttl' => 300, // 5 minutes
        ],
        'trending' => [
            'name' => 'Trending',
            'description' => 'Popular posts right now',
            'cache_ttl' => 900, // 15 minutes
        ],
        'discover' => [
            'name' => 'Discover',
            'description' => 'New content you might like',
            'cache_ttl' => 1800, // 30 minutes
        ],
        'bookmarks' => [
            'name' => 'Bookmarks',
            'description' => 'Your saved posts',
            'cache_ttl' => 180, // 3 minutes
        ],
    ];

    /**
     * Default feed settings.
     */
    const DEFAULT_PAGE_SIZE = 15;
    const MAX_PAGE_SIZE = 50;
    const CACHE_PREFIX = 'feed:';
    const TRENDING_HOURS = 24;
    const DISCOVER_SAMPLE_SIZE = 1000;

    /**
     * Generate feed for user with advanced algorithms.
     */
    public function generateFeed(User $user, array $options = []): array
    {
        $feedType = $options['type'] ?? 'chronological';
        $pageSize = min($options['per_page'] ?? self::DEFAULT_PAGE_SIZE, self::MAX_PAGE_SIZE);
        $cursor = $options['cursor'] ?? null;
        $filters = $options['filters'] ?? [];

        // Temporarily disable cache to avoid serialization issues
        // TODO: Implement proper model serialization for feed caching
        $cacheKey = $this->generateCacheKey($user, $feedType, $options);
        
        // if (!$options['bypass_cache'] ?? false) {
        //     $cachedFeed = $this->getCachedFeed($cacheKey);
        //     if ($cachedFeed && !$cursor) {
        //         return $this->applyCursorPagination($cachedFeed, $cursor, $pageSize);
        //     }
        // }

        // Generate fresh feed
        $feed = match ($feedType) {
            'chronological' => $this->generateChronologicalFeed($user, $options),
            'algorithmic' => $this->generateAlgorithmicFeed($user, $options),
            'following' => $this->generateFollowingFeed($user, $options),
            'trending' => $this->generateTrendingFeed($user, $options),
            'discover' => $this->generateDiscoverFeed($user, $options),
            'bookmarks' => $this->generateBookmarksFeed($user, $options),
            default => $this->generateChronologicalFeed($user, $options),
        };

        // Apply filters
        if (!empty($filters)) {
            $feed = $this->applyFilters($feed, $filters);
        }

        // Temporarily disable caching
        // $this->cacheFeed($cacheKey, $feed, $feedType);

        // Apply cursor pagination
        return $this->applyCursorPagination($feed, $cursor, $pageSize);
    }

    /**
     * Generate chronological feed (latest posts first).
     */
    protected function generateChronologicalFeed(User $user, array $options): Collection
    {
        $query = $this->getBaseFeedQuery($user);

        // Apply time period filter if specified
        if (isset($options['period'])) {
            $query = $this->applyPeriodFilter($query, $options['period']);
        }

        // Order by publication date (chronological)
        $query->orderBy('published_at', 'desc')
              ->orderBy('created_at', 'desc');

        // Include user's own posts and friends' posts
        $query->where(function ($q) use ($user) {
            $q->where('user_id', $user->id)
              ->orWhereIn('user_id', $this->getFriendIds($user))
              ->orWhere('visibility', 'public');
        });

        return $query->limit(100)->get(); // Get more than needed for caching
    }

    /**
     * Generate algorithmic feed based on user interests and engagement.
     */
    protected function generateAlgorithmicFeed(User $user, array $options): Collection
    {
        $query = $this->getBaseFeedQuery($user);

        // Get user's interaction history for scoring
        $userInteractions = $this->getUserInteractionMetrics($user);
        $friendIds = $this->getFriendIds($user);

        // Algorithmic scoring query
        $query->select('posts.*')
              ->addSelect(DB::raw('
                  (
                      (likes_count * 2) + 
                      (comments_count * 3) + 
                      (shares_count * 4) +
                      (views_count * 0.1) +
                      (CASE WHEN user_id IN (' . implode(',', $friendIds ?: [0]) . ') THEN 10 ELSE 0 END) +
                      (CASE WHEN visibility = "public" THEN 2 ELSE 0 END) +
                      (CASE WHEN created_at > NOW() - INTERVAL 6 HOUR THEN 5 ELSE 0 END) +
                      (CASE WHEN type IN ("book_review", "link") THEN 3 ELSE 0 END)
                  ) / GREATEST(TIMESTAMPDIFF(HOUR, created_at, NOW()), 1) as engagement_score
              '))
              ->where(function ($q) use ($user, $friendIds) {
                  $q->where('user_id', $user->id)
                    ->orWhereIn('user_id', $friendIds)
                    ->orWhere('visibility', 'public');
              })
              ->where('created_at', '>=', now()->subDays(7))
              ->orderByDesc('engagement_score')
              ->orderByDesc('published_at');

        return $query->limit(100)->get();
    }

    /**
     * Generate following feed (posts from followed users only).
     */
    protected function generateFollowingFeed(User $user, array $options): Collection
    {
        $followingIds = $this->getFollowingIds($user);
        
        if (empty($followingIds)) {
            return new Collection();
        }

        $query = $this->getBaseFeedQuery($user)
                      ->whereIn('user_id', $followingIds)
                      ->orderByDesc('published_at')
                      ->orderByDesc('created_at');

        return $query->limit(100)->get();
    }

    /**
     * Generate trending feed (popular posts in last 24 hours).
     */
    protected function generateTrendingFeed(User $user, array $options): Collection
    {
        $query = $this->getBaseFeedQuery($user);

        $query->select('posts.*')
              ->addSelect(DB::raw('
                  (
                      (likes_count * 1.5) + 
                      (comments_count * 2) + 
                      (shares_count * 3) +
                      (views_count * 0.05)
                  ) as trending_score
              '))
              ->where('created_at', '>=', now()->subHours(self::TRENDING_HOURS))
              ->where('visibility', 'public')
              ->having('trending_score', '>', 5)
              ->orderByDesc('trending_score')
              ->orderByDesc('published_at');

        return $query->limit(100)->get();
    }

    /**
     * Generate discover feed (new content based on interests).
     */
    protected function generateDiscoverFeed(User $user, array $options): Collection
    {
        $query = $this->getBaseFeedQuery($user);

        // Get user's interests and reading preferences
        $userInterests = $this->getUserInterests($user);
        $friendIds = $this->getFriendIds($user);

        $query->where('visibility', 'public')
              ->where('user_id', '!=', $user->id)
              ->whereNotIn('user_id', $friendIds)
              ->where('created_at', '>=', now()->subDays(3))
              ->where(function ($q) use ($userInterests) {
                  if (!empty($userInterests)) {
                      foreach ($userInterests as $interest) {
                          $q->orWhere('content', 'LIKE', "%{$interest}%")
                            ->orWhereJsonContains('metadata->tags', $interest);
                      }
                  }
                  $q->orWhere('likes_count', '>=', 10); // Popular posts
              })
              ->orderByDesc('likes_count')
              ->orderByDesc('published_at');

        return $query->limit(self::DISCOVER_SAMPLE_SIZE)->get()->shuffle()->take(100);
    }

    /**
     * Generate bookmarks feed (saved posts).
     */
    protected function generateBookmarksFeed(User $user, array $options): Collection
    {
        // This would require a bookmarks table - for now return empty collection
        // In a full implementation, this would join with user_bookmarks table
        return new Collection();
    }

    /**
     * Get base feed query with common optimizations.
     */
    protected function getBaseFeedQuery(User $user): Builder
    {
        return Post::with([
                'user:id,name,username',
                'user.profile:user_id,avatar_url',
                'mediaAttachments' => function ($query) {
                    $query->select('id', 'attachable_id', 'filename', 'type', 'size')
                          ->where('status', 'ready');
                },
                'comments' => function ($query) {
                    $query->select('id', 'post_id', 'user_id', 'content', 'created_at')
                          ->latest()
                          ->limit(3);
                },
                'comments.user:id,name,username',
            ])
            ->published()
            ->where('is_hidden', false)
            ->where('is_reported', false);
    }

    /**
     * Apply cursor-based pagination for performance.
     */
    protected function applyCursorPagination(Collection $posts, ?string $cursor, int $pageSize): array
    {
        $startIndex = 0;
        
        if ($cursor) {
            $decodedCursor = $this->decodeCursor($cursor);
            if (isset($decodedCursor['id'])) {
                $startIndex = $posts->search(function ($post) use ($decodedCursor) {
                    return $post->id === $decodedCursor['id'];
                });
                
                if ($startIndex !== false) {
                    $startIndex = $startIndex + 1;
                } else {
                    $startIndex = 0;
                }
            }
        }

        $paginatedPosts = $posts->slice($startIndex, $pageSize);
        $hasMore = $posts->count() > ($startIndex + $pageSize);
        
        $nextCursor = null;
        if ($hasMore && $paginatedPosts->isNotEmpty()) {
            $lastPost = $paginatedPosts->last();
            if (isset($lastPost->id) && isset($lastPost->published_at)) {
                $nextCursor = $this->encodeCursor([
                    'id' => $lastPost->id,
                    'timestamp' => $lastPost->published_at->timestamp,
                    'score' => $lastPost->engagement_score ?? $lastPost->published_at->timestamp,
                ]);
            }
        }

        return [
            'posts' => $paginatedPosts->values(),
            'pagination' => [
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'count' => $paginatedPosts->count(),
                'total_available' => $posts->count(),
            ],
        ];
    }

    /**
     * Apply filters to feed results.
     */
    protected function applyFilters(Collection $posts, array $filters): Collection
    {
        return $posts->filter(function ($post) use ($filters) {
            // Content type filter
            if (isset($filters['content_types']) && !in_array($post->type, $filters['content_types'])) {
                return false;
            }

            // Media filter
            if (isset($filters['has_media'])) {
                $hasMedia = $post->mediaAttachments->isNotEmpty();
                if ($filters['has_media'] !== $hasMedia) {
                    return false;
                }
            }

            // Date range filter
            if (isset($filters['date_from']) && $post->published_at < Carbon::parse($filters['date_from'])) {
                return false;
            }
            if (isset($filters['date_to']) && $post->published_at > Carbon::parse($filters['date_to'])) {
                return false;
            }

            // Engagement threshold filter
            if (isset($filters['min_engagement'])) {
                $engagement = $post->likes_count + $post->comments_count + $post->shares_count;
                if ($engagement < $filters['min_engagement']) {
                    return false;
                }
            }

            // Author filter
            if (isset($filters['exclude_authors']) && in_array($post->user_id, $filters['exclude_authors'])) {
                return false;
            }

            // Hashtag filter
            if (isset($filters['hashtags']) && !empty($filters['hashtags'])) {
                $postHashtags = $this->extractHashtags($post->content);
                if (empty(array_intersect($filters['hashtags'], $postHashtags))) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Apply period filter to query.
     */
    protected function applyPeriodFilter(Builder $query, string $period): Builder
    {
        $date = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->subDays(7),
        };

        return $query->where('published_at', '>=', $date);
    }

    /**
     * Get user's friend IDs for feed filtering.
     */
    protected function getFriendIds(User $user): array
    {
        return Cache::remember(
            "user:{$user->id}:friend_ids",
            3600, // 1 hour
            fn() => $user->friends()->pluck('id')->toArray()
        );
    }

    /**
     * Get user's following IDs for feed filtering.
     */
    protected function getFollowingIds(User $user): array
    {
        return Cache::remember(
            "user:{$user->id}:following_ids",
            3600, // 1 hour
            function () use ($user) {
                return $user->following()
                           ->active() // Only non-muted follows
                           ->pluck('following_id')
                           ->toArray();
            }
        );
    }

    /**
     * Get user interaction metrics for algorithmic scoring.
     */
    protected function getUserInteractionMetrics(User $user): array
    {
        return Cache::remember(
            "user:{$user->id}:interaction_metrics",
            1800, // 30 minutes
            function () use ($user) {
                return [
                    'total_likes' => DB::table('likes')->where('user_id', $user->id)->count(),
                    'total_comments' => DB::table('comments')->where('user_id', $user->id)->count(),
                    'favorite_content_types' => $this->getFavoriteContentTypes($user),
                    'peak_activity_hours' => $this->getPeakActivityHours($user),
                ];
            }
        );
    }

    /**
     * Get user's interests from profile and activity.
     */
    protected function getUserInterests(User $user): array
    {
        $interests = [];

        // From user profile
        if ($user->profile && $user->profile->reading_preferences) {
            $readingPrefs = $user->profile->reading_preferences;
            if (isset($readingPrefs['favorite_genres'])) {
                $interests = array_merge($interests, $readingPrefs['favorite_genres']);
            }
            if (isset($readingPrefs['favorite_authors'])) {
                $interests = array_merge($interests, $readingPrefs['favorite_authors']);
            }
        }

        // From recent interactions (posts user has liked/commented on)
        $recentInteractions = DB::table('likes')
            ->join('posts', 'likes.likeable_id', '=', 'posts.id')
            ->where('likes.user_id', $user->id)
            ->where('likes.likeable_type', Post::class)
            ->where('likes.created_at', '>=', now()->subDays(30))
            ->pluck('posts.content')
            ->take(50);

        foreach ($recentInteractions as $content) {
            $hashtags = $this->extractHashtags($content);
            $interests = array_merge($interests, $hashtags);
        }

        return array_unique($interests);
    }

    /**
     * Extract hashtags from content.
     */
    protected function extractHashtags(string $content): array
    {
        preg_match_all('/#([a-zA-Z0-9_]+)/', $content, $matches);
        return $matches[1] ?? [];
    }

    /**
     * Get user's favorite content types based on interactions.
     */
    protected function getFavoriteContentTypes(User $user): array
    {
        return DB::table('likes')
            ->join('posts', 'likes.likeable_id', '=', 'posts.id')
            ->where('likes.user_id', $user->id)
            ->where('likes.likeable_type', Post::class)
            ->where('likes.created_at', '>=', now()->subDays(30))
            ->select('posts.type', DB::raw('count(*) as count'))
            ->groupBy('posts.type')
            ->orderByDesc('count')
            ->pluck('type')
            ->toArray();
    }

    /**
     * Get user's peak activity hours.
     */
    protected function getPeakActivityHours(User $user): array
    {
        return DB::table('posts')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('HOUR(created_at) as hour, count(*) as count'))
            ->groupBy(DB::raw('HOUR(created_at)'))
            ->orderByDesc('count')
            ->limit(3)
            ->pluck('hour')
            ->toArray();
    }

    /**
     * Generate cache key for feed.
     */
    protected function generateCacheKey(User $user, string $feedType, array $options): string
    {
        $keyData = [
            'user_id' => $user->id,
            'type' => $feedType,
            'period' => $options['period'] ?? 'all',
            'filters' => md5(serialize($options['filters'] ?? [])),
        ];

        return self::CACHE_PREFIX . md5(serialize($keyData));
    }

    /**
     * Get cached feed.
     */
    protected function getCachedFeed(string $cacheKey): ?Collection
    {
        try {
            $cached = Cache::get($cacheKey);
            if (!$cached) {
                return null;
            }
            
            // Convert cached arrays back to Post models
            $posts = collect($cached)->map(function ($postData) {
                // Create a Post instance from the cached array data
                $post = new Post();
                $post->fill($postData);
                $post->exists = true;
                $post->setRawAttributes($postData, true);
                
                // Handle relationships if they exist
                if (isset($postData['user'])) {
                    $user = new User();
                    $user->fill($postData['user']);
                    $user->exists = true;
                    $post->setRelation('user', $user);
                }
                
                if (isset($postData['media_attachments'])) {
                    $post->setRelation('mediaAttachments', collect($postData['media_attachments']));
                }
                
                if (isset($postData['comments'])) {
                    $post->setRelation('comments', collect($postData['comments']));
                }
                
                return $post;
            });
            
            return $posts;
        } catch (\Exception $e) {
            // If there's any issue with cache deserialization, return null
            return null;
        }
    }

    /**
     * Cache feed results.
     */
    protected function cacheFeed(string $cacheKey, Collection $feed, string $feedType): void
    {
        $ttl = self::FEED_TYPES[$feedType]['cache_ttl'] ?? 300;
        
        try {
            // Convert models to arrays for caching
            $cacheData = $feed->map(function ($post) {
                $postArray = $post->toArray();
                
                // Include essential relationships
                if ($post->relationLoaded('user')) {
                    $postArray['user'] = $post->user->toArray();
                }
                
                if ($post->relationLoaded('mediaAttachments')) {
                    $postArray['media_attachments'] = $post->mediaAttachments->toArray();
                }
                
                if ($post->relationLoaded('comments')) {
                    $postArray['comments'] = $post->comments->toArray();
                }
                
                return $postArray;
            })->toArray();
            
            Cache::put($cacheKey, $cacheData, $ttl);
        } catch (\Exception $e) {
            // Log error but don't fail the request
        }
    }

    /**
     * Encode cursor for pagination.
     */
    protected function encodeCursor(array $data): string
    {
        return base64_encode(json_encode($data));
    }

    /**
     * Decode cursor for pagination.
     */
    protected function decodeCursor(string $cursor): array
    {
        try {
            return json_decode(base64_decode($cursor), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get feed statistics for analytics.
     */
    public function getFeedStats(User $user, string $feedType = 'chronological'): array
    {
        $cacheKey = "feed_stats:{$user->id}:{$feedType}";
        
        return Cache::remember($cacheKey, 1800, function () use ($user, $feedType) {
            $feed = $this->generateFeed($user, ['type' => $feedType, 'bypass_cache' => true]);
            
            return [
                'total_posts' => $feed['posts']->count(),
                'content_type_breakdown' => $feed['posts']->groupBy('type')->map->count(),
                'average_engagement' => $feed['posts']->avg(function ($post) {
                    return $post->likes_count + $post->comments_count + $post->shares_count;
                }),
                'friends_posts_count' => $feed['posts']->whereIn('user_id', $this->getFriendIds($user))->count(),
                'public_posts_count' => $feed['posts']->where('visibility', 'public')->count(),
                'generated_at' => now()->toISOString(),
            ];
        });
    }

    /**
     * Invalidate user's feed cache.
     */
    public function invalidateFeedCache(User $user): void
    {
        $patterns = [
            self::CACHE_PREFIX . "*user_id:{$user->id}*",
            "user:{$user->id}:*",
            "feed_stats:{$user->id}:*",
        ];

        foreach ($patterns as $pattern) {
            try {
                $keys = Redis::keys($pattern);
                if (!empty($keys)) {
                    Redis::del($keys);
                }
            } catch (\Exception $e) {
                // Log error but continue
            }
        }
    }

    /**
     * Warm up feed cache for user.
     */
    public function warmupFeedCache(User $user): void
    {
        foreach (array_keys(self::FEED_TYPES) as $feedType) {
            try {
                $this->generateFeed($user, [
                    'type' => $feedType,
                    'per_page' => self::DEFAULT_PAGE_SIZE,
                ]);
            } catch (\Exception $e) {
                // Log error but continue with other feed types
            }
        }
    }

    /**
     * Get available feed types for user.
     */
    public function getAvailableFeedTypes(User $user): array
    {
        $feedTypes = self::FEED_TYPES;
        
        // Customize based on user preferences or features
        if (!$user->profile || !$user->profile->show_algorithmic_feed) {
            unset($feedTypes['algorithmic']);
        }

        return array_map(function ($type, $config) {
            return array_merge($config, ['key' => $type]);
        }, array_keys($feedTypes), $feedTypes);
    }
} 