<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreatePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Services\TextFormattingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PostController extends Controller
{
    public function __construct(
        protected TextFormattingService $textFormattingService
    ) {}

    /**
     * Get posts feed for authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'type' => 'sometimes|in:all,friends,groups,following',
            'period' => 'sometimes|in:today,week,month,year',
            'sort' => 'sometimes|in:newest,popular,trending',
            'per_page' => 'sometimes|integer|min:5|max:50',
        ]);

        $user = Auth::user();
        $query = Post::with(['user.profile', 'group', 'mediaAttachments', 'comments.user'])
                     ->visibleTo($user);

        // Filter by type
        switch ($request->input('type', 'all')) {
            case 'friends':
                $query->where('visibility', 'friends');
                break;
            case 'groups':
                $query->where('visibility', 'group')->whereNotNull('group_id');
                break;
            case 'following':
                // Posts from users the current user follows (friends)
                $friendIds = $user->friends()->pluck('id');
                $query->whereIn('user_id', $friendIds);
                break;
        }

        // Filter by period
        if ($request->has('period')) {
            $query->fromPeriod($request->input('period'));
        }

        // Sort posts
        switch ($request->input('sort', 'newest')) {
            case 'popular':
                $query->popular();
                break;
            case 'trending':
                $query->trending();
                break;
            default:
                $query->latest('published_at');
        }

        $posts = $query->paginate($request->input('per_page', 15));

        return PostResource::collection($posts);
    }

    /**
     * Get a specific post.
     */
    public function show(Post $post): PostResource
    {
        $user = Auth::user();

        if (!$post->isVisibleTo($user)) {
            abort(404, 'Post not found');
        }

        $post->load(['user.profile', 'group', 'mediaAttachments', 'comments.user.profile', 'likes.user']);

        return new PostResource($post);
    }

    /**
     * Create a new post.
     */
    public function store(CreatePostRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $validated = $request->validated();

            // Format content if provided
            if (isset($validated['content'])) {
                $validated['content'] = $this->textFormattingService->format($validated['content']);
            }

            // Set default visibility based on user's profile settings
            if (!isset($validated['visibility'])) {
                $visibilityService = app(\App\Services\ContentVisibilityService::class);
                $validated['visibility'] = $visibilityService->getDefaultVisibilityForUser($user, 'post');
            }

            // Handle scheduled posts
            if (isset($validated['scheduled_at'])) {
                $validated['is_scheduled'] = true;
                $validated['published_at'] = $validated['scheduled_at'];
                unset($validated['scheduled_at']);
            } else {
                $validated['published_at'] = now();
            }

            // Create the post
            $post = $user->posts()->create($validated);

            // Handle media attachments
            if ($request->has('media_ids')) {
                $this->attachMedia($post, $request->input('media_ids'));
            }

            DB::commit();

            $post->load(['user.profile', 'group', 'mediaAttachments']);

            return response()->json([
                'success' => true,
                'message' => $post->is_scheduled ? 'Post scheduled successfully' : 'Post created successfully',
                'data' => new PostResource($post),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create post',
                'errors' => ['system' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Update an existing post.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $user = Auth::user();

        if (!$post->canEditBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to edit this post',
            ], 403);
        }

        try {
            DB::beginTransaction();

            $validated = $request->validated();

            // Format content if provided
            if (isset($validated['content'])) {
                $validated['content'] = $this->textFormattingService->format($validated['content']);
            }

            // Handle scheduled posts
            if (isset($validated['scheduled_at'])) {
                $validated['is_scheduled'] = true;
                $validated['published_at'] = $validated['scheduled_at'];
                unset($validated['scheduled_at']);
            }

            $post->update($validated);

            // Handle media attachments
            if ($request->has('media_ids')) {
                // Remove existing media attachments
                $post->mediaAttachments()->delete();
                // Attach new ones
                $this->attachMedia($post, $request->input('media_ids'));
            }

            DB::commit();

            $post->load(['user.profile', 'group', 'mediaAttachments']);

            return response()->json([
                'success' => true,
                'message' => 'Post updated successfully',
                'data' => new PostResource($post),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update post',
                'errors' => ['system' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Delete a post.
     */
    public function destroy(Post $post): JsonResponse
    {
        $user = Auth::user();

        if (!$post->canEditBy($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this post',
            ], 403);
        }

        try {
            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'Post deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete post',
                'errors' => ['system' => [$e->getMessage()]],
            ], 422);
        }
    }

    /**
     * Get posts by a specific user.
     */
    public function userPosts(Request $request, User $user): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:5|max:50',
        ]);

        $currentUser = Auth::user();
        
        $query = $user->posts()
                     ->with(['user.profile', 'group', 'mediaAttachments', 'comments.user'])
                     ->visibleTo($currentUser)
                     ->latest('published_at');

        $posts = $query->paginate($request->input('per_page', 15));

        return PostResource::collection($posts);
    }

    /**
     * Get posts for a specific group.
     */
    public function groupPosts(Request $request, int $groupId): AnonymousResourceCollection
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:5|max:50',
        ]);

        $user = Auth::user();
        
        $query = Post::with(['user.profile', 'group', 'mediaAttachments', 'comments.user'])
                     ->where('group_id', $groupId)
                     ->visibleTo($user)
                     ->latest('published_at');

        $posts = $query->paginate($request->input('per_page', 15));

        return PostResource::collection($posts);
    }

    /**
     * Share or repost a post.
     */
    public function share(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'share_type' => 'sometimes|string|in:repost,quote_repost,external,link_share,private_share',
            'platform' => 'sometimes|string|in:twitter,facebook,linkedin,reddit,whatsapp,telegram',
            'content' => 'sometimes|string|max:500',
            'shared_to_user_id' => 'sometimes|integer|exists:users,id',
            'shared_to_group_id' => 'sometimes|integer|exists:groups,id',
            'visibility' => 'sometimes|string|in:public,friends,private',
            'is_quote_share' => 'sometimes|boolean',
        ]);

        try {
            $user = Auth::user();

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found or not accessible',
                ], 404);
            }

            // Check if post allows resharing
            if (!$post->allow_resharing) {
                return response()->json([
                    'success' => false,
                    'message' => 'This post cannot be shared',
                ], 403);
            }

            // Prevent users from reposting their own content (for internal shares)
            $shareType = $request->input('share_type', 'external');
            if (in_array($shareType, ['repost', 'quote_repost', 'internal']) && $post->user_id === $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot repost your own content',
                ], 422);
            }

            // Check for existing share to prevent duplicates (for internal shares)
            if (in_array($shareType, ['repost', 'quote_repost', 'internal'])) {
                $existingShare = \App\Models\Share::where([
                    'user_id' => $user->id,
                    'shareable_id' => $post->id,
                    'shareable_type' => Post::class,
                    'share_type' => $shareType,
                ])->first();

                if ($existingShare) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You have already shared this post',
                    ], 422);
                }
            }

            DB::beginTransaction();

            // Create share record
            $shareData = [
                'user_id' => $user->id,
                'shareable_id' => $post->id,
                'shareable_type' => Post::class,
                'share_type' => $shareType,
                'platform' => $request->input('platform'),
                'content' => $request->input('content'),
                'shared_to_user_id' => $request->input('shared_to_user_id'),
                'shared_to_group_id' => $request->input('shared_to_group_id'),
                'visibility' => $request->input('visibility', $shareType === 'private_share' ? 'private' : 'public'),
                'is_quote_share' => $request->boolean('is_quote_share') || !empty($request->input('content')),
                'is_private_share' => !empty($request->input('shared_to_user_id')),
            ];

            $share = \App\Models\Share::create($shareData);

            DB::commit();

            // Load relationships for response
            $share->load(['user', 'shareable', 'sharedToUser', 'sharedToGroup']);

            return response()->json([
                'success' => true,
                'message' => 'Content shared successfully',
                'data' => [
                    'share_id' => $share->id,
                    'share_type' => $share->share_type,
                    'platform' => $share->platform,
                    'is_quote_share' => $share->is_quote_share,
                    'shares_count' => $post->fresh()->shares_count,
                    'share_url' => $share->getShareUrl(),
                    'shared_at' => $share->shared_at->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to share content',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get shares for a specific post.
     */
    public function shares(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'type' => 'sometimes|string|in:all,reposts,external,quote_shares',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $user = Auth::user();

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Post not found or not accessible',
                ], 404);
            }

            $query = \App\Models\Share::where([
                'shareable_id' => $post->id,
                'shareable_type' => Post::class,
            ])
            ->with(['user', 'sharedToUser', 'sharedToGroup'])
            ->visibleTo($user)
            ->orderBy('shared_at', 'desc');

            // Filter by type
            $type = $request->input('type', 'all');
            switch ($type) {
                case 'reposts':
                    $query->reposts();
                    break;
                case 'external':
                    $query->external();
                    break;
                case 'quote_shares':
                    $query->quoteShares();
                    break;
            }

            $perPage = $request->integer('per_page', 20);
            $shares = $query->paginate($perPage);

                         return response()->json([
                 'success' => true,
                 'data' => [
                     'shares' => \App\Http\Resources\ShareResource::collection($shares->items()),
                    'pagination' => [
                        'current_page' => $shares->currentPage(),
                        'last_page' => $shares->lastPage(),
                        'per_page' => $shares->perPage(),
                        'total' => $shares->total(),
                        'has_more_pages' => $shares->hasMorePages(),
                    ],
                    'type_filter' => $type,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch shares',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a share (unshare).
     */
    public function unshare(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'share_id' => 'sometimes|integer|exists:shares,id',
            'share_type' => 'sometimes|string|in:repost,quote_repost,external,link_share,private_share',
        ]);

        try {
            $user = Auth::user();

            // Find the share to delete
            $query = \App\Models\Share::where([
                'user_id' => $user->id,
                'shareable_id' => $post->id,
                'shareable_type' => Post::class,
            ]);

            if ($request->has('share_id')) {
                $query->where('id', $request->input('share_id'));
            } elseif ($request->has('share_type')) {
                $query->where('share_type', $request->input('share_type'));
            } else {
                // Delete most recent share
                $query->orderBy('shared_at', 'desc');
            }

            $share = $query->first();

            if (!$share) {
                return response()->json([
                    'success' => false,
                    'message' => 'Share not found',
                ], 404);
            }

            DB::beginTransaction();

            $share->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Share removed successfully',
                'data' => [
                    'shares_count' => $post->fresh()->shares_count,
                    'unshared_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove share',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle like/unlike for a post.
     */
    public function toggleLike(Request $request, Post $post): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user can interact with this post
        if (!$post->isVisibleTo($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found or not accessible',
            ], 404);
        }

        $existingLike = $post->likes()->where('user_id', $user->id)->first();

        if ($existingLike) {
            // Unlike the post
            $existingLike->delete();
            $post->decrement('likes_count');
            $action = 'unliked';
        } else {
            // Like the post
            $like = $post->likes()->create([
                'user_id' => $user->id,
            ]);
            $post->increment('likes_count');
            $action = 'liked';

            // Create notification if user liked someone else's post
            if ($post->user_id !== $user->id) {
                try {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->createLikeNotification($user, $post);
                } catch (\Exception $e) {
                    // Log error but don't fail the like operation
                    Log::warning('Failed to create like notification: ' . $e->getMessage());
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Post {$action} successfully",
            'data' => [
                'liked' => $action === 'liked',
                'likes_count' => $post->fresh()->likes_count,
            ],
        ]);
    }

    /**
     * Get post analytics (for post owners).
     */
    public function analytics(Post $post): JsonResponse
    {
        $user = Auth::user();

        if ($post->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this post\'s analytics',
            ], 403);
        }

        $analytics = [
            'views' => $post->views_count ?? 0,
            'likes' => $post->likes_count,
            'comments' => $post->comments_count,
            'shares' => $post->shares_count,
            'engagement_rate' => $this->calculateEngagementRate($post),
            'reach' => $this->calculateReach($post),
            'top_commenters' => $this->getTopCommenters($post),
            'performance_vs_average' => $this->compareToUserAverage($post),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Report a post.
     */
    public function report(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|in:spam,harassment,inappropriate,violence,copyright,other',
            'details' => 'sometimes|string|max:500',
        ]);

        $user = Auth::user();

        // Check if user already reported this post
        $existingReport = DB::table('post_reports')
                           ->where('post_id', $post->id)
                           ->where('user_id', $user->id)
                           ->exists();

        if ($existingReport) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reported this post',
            ], 422);
        }

        // Create report
        DB::table('post_reports')->insert([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'reason' => $request->input('reason'),
            'details' => $request->input('details'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Mark post as reported if it has multiple reports
        $reportCount = DB::table('post_reports')->where('post_id', $post->id)->count();
        if ($reportCount >= 3) {
            $post->update(['is_reported' => true]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Post reported successfully. Thank you for helping keep our community safe.',
        ]);
    }

    /**
     * Attach media to a post.
     */
    private function attachMedia(Post $post, array $mediaIds): void
    {
        $user = Auth::user();
        
        // Get media attachments that belong to the user and are not yet attached
        $mediaAttachments = \App\Models\MediaAttachment::whereIn('id', $mediaIds)
                                                      ->where('user_id', $user->id)
                                                      ->whereNull('attachable_id')
                                                      ->get();

        foreach ($mediaAttachments as $media) {
            $media->update([
                'attachable_type' => Post::class,
                'attachable_id' => $post->id,
            ]);
        }
    }

    /**
     * Calculate engagement rate for a post.
     */
    private function calculateEngagementRate(Post $post): float
    {
        $totalEngagements = $post->likes_count + $post->comments_count + $post->shares_count;
        $views = $post->views_count ?? 1;
        
        return round(($totalEngagements / $views) * 100, 2);
    }

    /**
     * Calculate reach for a post.
     */
    private function calculateReach(Post $post): array
    {
        // This would normally involve complex calculations
        // For now, return estimated reach based on visibility and user's network
        $baseReach = match ($post->visibility) {
            'public' => $post->user->profile->friends_count * 2,
            'friends' => $post->user->profile->friends_count,
            'group' => $post->group?->members_count ?? 0,
            default => 1,
        };

        return [
            'estimated' => $baseReach,
            'actual' => $post->views_count ?? 0,
        ];
    }

    /**
     * Get top commenters for a post.
     */
    private function getTopCommenters(Post $post): array
    {
        return $post->comments()
                   ->select('user_id', DB::raw('count(*) as comment_count'))
                   ->with('user:id,name,username')
                   ->groupBy('user_id')
                   ->orderByDesc('comment_count')
                   ->limit(5)
                   ->get()
                   ->toArray();
    }

    /**
     * Compare post performance to user's average.
     */
    private function compareToUserAverage(Post $post): array
    {
        $userAverages = $post->user->posts()
                                  ->selectRaw('
                                      AVG(likes_count) as avg_likes,
                                      AVG(comments_count) as avg_comments,
                                      AVG(shares_count) as avg_shares
                                  ')
                                  ->where('created_at', '>=', now()->subMonths(3))
                                  ->first();

        return [
            'likes' => $userAverages->avg_likes ? round(($post->likes_count / $userAverages->avg_likes) * 100, 1) : 100,
            'comments' => $userAverages->avg_comments ? round(($post->comments_count / $userAverages->avg_comments) * 100, 1) : 100,
            'shares' => $userAverages->avg_shares ? round(($post->shares_count / $userAverages->avg_shares) * 100, 1) : 100,
        ];
    }
} 