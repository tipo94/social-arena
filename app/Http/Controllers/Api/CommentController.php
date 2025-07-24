<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Comment;
use App\Http\Requests\CreateCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Services\TextFormattingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommentController extends Controller
{
    public function __construct(
        protected TextFormattingService $textFormattingService
    ) {}

    /**
     * Get comments for a specific post.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'sort' => 'sometimes|string|in:newest,oldest,popular',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'cursor' => 'sometimes|string',
            'include_replies' => 'sometimes|boolean',
            'depth' => 'sometimes|integer|min:0|max:' . Comment::MAX_DEPTH,
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

            $query = $post->comments()
                ->with(['user.profile', 'likes'])
                ->visible()
                ->when($request->has('depth'), function ($query) use ($request) {
                    return $query->byDepth($request->integer('depth'));
                });

            // Apply sorting
            $sort = $request->input('sort', 'newest');
            switch ($sort) {
                case 'oldest':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'popular':
                    $query->popular();
                    break;
                case 'newest':
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            // Handle nested replies
            if ($request->boolean('include_replies', false)) {
                $query->with(['allReplies.user.profile', 'allReplies.likes']);
            }

            $perPage = $request->integer('per_page', 20);
            $comments = $query->paginate($perPage);

            // Build threaded comment tree if including replies
            if ($request->boolean('include_replies', false)) {
                $commentTree = Comment::buildTree($comments->getCollection());
                $comments->setCollection($commentTree);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'comments' => CommentResource::collection($comments->items()),
                    'pagination' => [
                        'current_page' => $comments->currentPage(),
                        'last_page' => $comments->lastPage(),
                        'per_page' => $comments->perPage(),
                        'total' => $comments->total(),
                        'has_more_pages' => $comments->hasMorePages(),
                        'next_page_url' => $comments->nextPageUrl(),
                        'prev_page_url' => $comments->previousPageUrl(),
                    ],
                    'sort' => $sort,
                    'include_replies' => $request->boolean('include_replies', false),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comments',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Create a new comment on a post.
     */
    public function store(CreateCommentRequest $request, Post $post): JsonResponse
    {
        $user = Auth::user();
        
        // Check if user can comment on this post
        if (!$post->isVisibleTo($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Post not found or commenting not allowed',
            ], 404);
        }

        if (!$post->allow_comments) {
            return response()->json([
                'success' => false,
                'message' => 'Comments are disabled for this post',
            ], 403);
        }

        $validated = $request->validated();
        
        // Handle parent comment and depth validation
        $parentComment = null;
        $depth = 0;
        $path = '';
        
        if (!empty($validated['parent_id'])) {
            $parentComment = Comment::where('post_id', $post->id)
                                  ->where('id', $validated['parent_id'])
                                  ->first();
            
            if (!$parentComment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parent comment not found',
                ], 404);
            }
            
            $depth = $parentComment->depth + 1;
            
            // Check maximum nesting depth
            if ($depth > Comment::MAX_DEPTH) {
                return response()->json([
                    'success' => false,
                    'message' => 'Maximum comment nesting depth exceeded',
                ], 422);
            }
            
            // Build materialized path
            $path = $parentComment->path ? $parentComment->path . '.' . $parentComment->id : (string)$parentComment->id;
        }

        try {
            DB::beginTransaction();

            // Format and validate content
            $formattedContent = $this->textFormattingService->formatComment($validated['content']);
            
            // Create the comment
            $comment = Comment::create([
                'user_id' => $user->id,
                'post_id' => $post->id,
                'parent_id' => $validated['parent_id'] ?? null,
                'content' => $formattedContent,
                'type' => $validated['type'] ?? 'text',
                'depth' => $depth,
                'path' => $path,
            ]);

            // Update counters
            $post->increment('comments_count');
            
            if ($parentComment) {
                $parentComment->increment('replies_count');
            }

            // Load relations for response
            $comment->load(['user.profile', 'post', 'parent']);

            DB::commit();

            // Create notification if user commented on someone else's post
            if ($post->user_id !== $user->id) {
                try {
                    $notificationService = app(\App\Services\NotificationService::class);
                    $notificationService->createCommentNotification($user, $comment);
                } catch (\Exception $e) {
                    // Log error but don't fail the comment creation
                    Log::warning('Failed to create comment notification: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Comment created successfully',
                'data' => new CommentResource($comment),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Display a specific comment.
     */
    public function show(Post $post, Comment $comment): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not accessible',
                ], 404);
            }

            // Check if comment is hidden and user doesn't have permission
            if ($comment->is_hidden && !$comment->canEditBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            $comment->load(['user.profile', 'post', 'parent', 'replies.user.profile', 'likes']);

            return response()->json([
                'success' => true,
                'data' => new CommentResource($comment),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update a comment.
     */
    public function update(UpdateCommentRequest $request, Post $post, Comment $comment): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check permissions
            if (!$comment->canEditBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to edit this comment',
                ], 403);
            }

            DB::beginTransaction();

            $validated = $request->validated();
            
            // Format content if provided
            if (isset($validated['content'])) {
                $validated['content'] = $this->textFormattingService->format($validated['content']);
            }

            // Track that comment was edited
            $originalContent = $comment->content;
            $comment->update($validated);

            // Add edit tracking if content changed
            if (isset($validated['content']) && $originalContent !== $validated['content']) {
                $comment->update([
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $comment->load(['user.profile', 'post', 'parent', 'likes']);

            return response()->json([
                'success' => true,
                'message' => 'Comment updated successfully',
                'data' => new CommentResource($comment),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Delete a comment.
     */
    public function destroy(Request $request, Post $post, Comment $comment): JsonResponse
    {
        $request->validate([
            'reason' => 'sometimes|string|max:500',
        ]);

        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check permissions
            if (!$comment->canDeleteBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to delete this comment',
                ], 403);
            }

            DB::beginTransaction();

            // Store deletion reason if provided
            if ($request->has('reason')) {
                $comment->update([
                    'moderated_at' => now(),
                    'moderated_by' => $user->id,
                ]);
            }

            // Soft delete the comment (this will trigger model events)
            $comment->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment deleted successfully',
                'data' => [
                    'comment_id' => $comment->id,
                    'deleted_at' => $comment->deleted_at?->toISOString(),
                    'can_be_restored' => $user->id === $comment->user_id || (method_exists($user, 'isAdmin') && $user->isAdmin()),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Like or unlike a comment.
     */
    public function toggleLike(Post $post, Comment $comment): JsonResponse
    {
        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not accessible',
                ], 404);
            }

            $existing = $comment->likes()->where('user_id', $user->id)->first();

            if ($existing) {
                // Unlike
                $existing->delete();
                $comment->decrementLikes();
                $action = 'unliked';
                $isLiked = false;
            } else {
                // Like
                $comment->likes()->create([
                    'user_id' => $user->id,
                    'type' => 'like',
                ]);
                $comment->incrementLikes();
                $action = 'liked';
                $isLiked = true;
            }

            return response()->json([
                'success' => true,
                'message' => "Comment {$action} successfully",
                'data' => [
                    'comment_id' => $comment->id,
                    'likes_count' => $comment->fresh()->likes_count,
                    'is_liked_by_user' => $isLiked,
                    'action' => $action,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to like/unlike comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Report a comment.
     */
    public function report(Request $request, Post $post, Comment $comment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'category' => 'sometimes|string|in:spam,harassment,inappropriate,misinformation,other',
        ]);

        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not accessible',
                ], 404);
            }

            // Check if already reported by this user
            // Note: You might want to create a CommentReport model for this
            if ($comment->is_reported) {
                return response()->json([
                    'success' => false,
                    'message' => 'This comment has already been reported',
                ], 422);
            }

            DB::beginTransaction();

            // Mark comment as reported
            $comment->update([
                'is_reported' => true,
                'moderated_at' => now(),
            ]);

            // Here you could create a CommentReport record with details
            // CommentReport::create([...]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Comment reported successfully. Our moderation team will review it.',
                'data' => [
                    'comment_id' => $comment->id,
                    'reported_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to report comment',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get comment replies.
     */
    public function replies(Request $request, Post $post, Comment $comment): JsonResponse
    {
        $request->validate([
            'sort' => 'sometimes|string|in:newest,oldest,popular',
            'per_page' => 'sometimes|integer|min:1|max:50',
        ]);

        try {
            $user = Auth::user();

            // Verify comment belongs to post
            if ($comment->post_id !== $post->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not found',
                ], 404);
            }

            // Check if user can view the post
            if (!$post->isVisibleTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Comment not accessible',
                ], 404);
            }

            $query = $comment->replies()
                ->with(['user.profile', 'likes'])
                ->visible();

            // Apply sorting
            $sort = $request->input('sort', 'oldest');
            switch ($sort) {
                case 'newest':
                    $query->orderBy('created_at', 'desc');
                    break;
                case 'popular':
                    $query->popular();
                    break;
                case 'oldest':
                default:
                    $query->orderBy('created_at', 'asc');
                    break;
            }

            $perPage = $request->integer('per_page', 10);
            $replies = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'replies' => CommentResource::collection($replies->items()),
                    'pagination' => [
                        'current_page' => $replies->currentPage(),
                        'last_page' => $replies->lastPage(),
                        'per_page' => $replies->perPage(),
                        'total' => $replies->total(),
                        'has_more_pages' => $replies->hasMorePages(),
                    ],
                    'parent_comment_id' => $comment->id,
                    'sort' => $sort,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch replies',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
} 