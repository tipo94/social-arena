<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Friendship;
use App\Http\Requests\SendFriendRequestRequest;
use App\Http\Resources\FriendshipResource;
use App\Services\FriendSuggestionService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FriendshipController extends Controller
{
    public function __construct(
        protected FriendSuggestionService $friendSuggestionService
    ) {}
    /**
     * Get current user's friends list.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'sometimes|string|in:accepted,pending,blocked,declined,all',
            'type' => 'sometimes|string|in:sent,received,mutual',
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:name,recent,mutual_friends',
            'order' => 'sometimes|string|in:asc,desc',
        ]);

        try {
            $user = Auth::user();
            $status = $request->input('status', 'accepted');
            $type = $request->input('type', 'mutual');
            $search = $request->input('search');
            $perPage = $request->input('per_page', 20);
            $sort = $request->input('sort', 'name');
            $order = $request->input('order', 'asc');

            $query = Friendship::query()
                ->with(['user.profile', 'friend.profile']);

            // Filter by user involvement and status
            if ($status === 'all') {
                $query->involvingUser($user->id);
            } else {
                switch ($type) {
                    case 'sent':
                        $query->sentBy($user->id)->withStatus($status);
                        break;
                    case 'received':
                        $query->receivedBy($user->id)->withStatus($status);
                        break;
                    case 'mutual':
                    default:
                        $query->involvingUser($user->id)->withStatus($status);
                        break;
                }
            }

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search, $user) {
                    $q->whereHas('user', function ($userQ) use ($search, $user) {
                        if ($user->id !== 0) { // Ensure we don't filter out our own records
                            $userQ->where('name', 'like', "%{$search}%")
                                  ->orWhere('username', 'like', "%{$search}%");
                        }
                    })->orWhereHas('friend', function ($friendQ) use ($search) {
                        $friendQ->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                    });
                });
            }

            // Apply sorting
            switch ($sort) {
                case 'recent':
                    $query->orderBy($status === 'accepted' ? 'accepted_at' : 'requested_at', $order);
                    break;
                case 'mutual_friends':
                    $query->orderBy('mutual_friends_count', $order);
                    break;
                case 'name':
                default:
                    // Sort by the other user's name (not current user)
                    $query->leftJoin('users as u1', function ($join) use ($user) {
                        $join->on('u1.id', '=', 'friendships.user_id')
                             ->where('friendships.friend_id', '=', $user->id);
                    })->leftJoin('users as u2', function ($join) use ($user) {
                        $join->on('u2.id', '=', 'friendships.friend_id')
                             ->where('friendships.user_id', '=', $user->id);
                    })->orderBy(DB::raw('COALESCE(u1.name, u2.name)'), $order);
                    break;
            }

            $friendships = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'friendships' => FriendshipResource::collection($friendships->items()),
                    'pagination' => [
                        'current_page' => $friendships->currentPage(),
                        'last_page' => $friendships->lastPage(),
                        'per_page' => $friendships->perPage(),
                        'total' => $friendships->total(),
                        'has_more_pages' => $friendships->hasMorePages(),
                    ],
                    'filters' => [
                        'status' => $status,
                        'type' => $type,
                        'search' => $search,
                        'sort' => $sort,
                        'order' => $order,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch friendships',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Send a friend request.
     */
    public function store(SendFriendRequestRequest $request): JsonResponse
    {
        try {
            $sender = Auth::user();
            $recipientId = $request->validated()['user_id'];
            $recipient = User::findOrFail($recipientId);

            // Check if they can be friends
            if (!Friendship::canBeFriends($sender, $recipient)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot send friend request to this user',
                ], 422);
            }

            DB::beginTransaction();

            // Create the friend request
            $friendship = Friendship::create([
                'user_id' => $sender->id,
                'friend_id' => $recipient->id,
                'status' => Friendship::STATUS_PENDING,
            ]);

            DB::commit();

            // Load relationships for response
            $friendship->load(['user.profile', 'friend.profile']);

            return response()->json([
                'success' => true,
                'message' => 'Friend request sent successfully',
                'data' => new FriendshipResource($friendship),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to send friend request',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get a specific friendship.
     */
    public function show(Friendship $friendship): JsonResponse
    {
        $user = Auth::user();

        // Check if user can view this friendship
        if (!$friendship->isVisibleTo($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Friendship not found or not accessible',
            ], 404);
        }

        $friendship->load(['user.profile', 'friend.profile']);

        return response()->json([
            'success' => true,
            'data' => new FriendshipResource($friendship),
        ]);
    }

    /**
     * Accept a friend request.
     */
    public function accept(Friendship $friendship): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user can modify this friendship
            if (!$friendship->canBeModifiedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot accept this friend request',
                ], 403);
            }

            // Check if friendship is pending
            if ($friendship->status !== Friendship::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'This friend request cannot be accepted',
                ], 422);
            }

            DB::beginTransaction();

            $result = $friendship->accept();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to accept friend request',
                ], 422);
            }

            DB::commit();

            // Load relationships for response
            $friendship->load(['user.profile', 'friend.profile']);

            return response()->json([
                'success' => true,
                'message' => 'Friend request accepted successfully',
                'data' => new FriendshipResource($friendship),
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to accept friend request',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Decline a friend request.
     */
    public function decline(Friendship $friendship): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user can modify this friendship
            if (!$friendship->canBeModifiedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot decline this friend request',
                ], 403);
            }

            // Check if friendship is pending
            if ($friendship->status !== Friendship::STATUS_PENDING) {
                return response()->json([
                    'success' => false,
                    'message' => 'This friend request cannot be declined',
                ], 422);
            }

            DB::beginTransaction();

            $result = $friendship->decline();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to decline friend request',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Friend request declined successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to decline friend request',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Block a user.
     */
    public function block(Friendship $friendship): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user can modify this friendship
            if (!$friendship->canBeModifiedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot block this user',
                ], 403);
            }

            DB::beginTransaction();

            $result = $friendship->block();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to block user',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User blocked successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to block user',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unblock a user.
     */
    public function unblock(Friendship $friendship): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user can modify this friendship
            if (!$friendship->canBeModifiedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot unblock this user',
                ], 403);
            }

            // Check if friendship is blocked
            if ($friendship->status !== Friendship::STATUS_BLOCKED) {
                return response()->json([
                    'success' => false,
                    'message' => 'This user is not blocked',
                ], 422);
            }

            DB::beginTransaction();

            $result = $friendship->unblock();

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unblock user',
                ], 422);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User unblocked successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to unblock user',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Remove/unfriend a user.
     */
    public function destroy(Friendship $friendship): JsonResponse
    {
        try {
            $user = Auth::user();

            // Check if user can modify this friendship
            if (!$friendship->canBeModifiedBy($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot remove this friendship',
                ], 403);
            }

            DB::beginTransaction();

            $friendship->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Friendship removed successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove friendship',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get mutual friends between current user and another user.
     */
    public function mutualFriends(User $user): JsonResponse
    {
        try {
            $currentUser = Auth::user();

            // Find friendship between users
            $friendship = Friendship::betweenUsers($currentUser->id, $user->id)
                                   ->accepted()
                                   ->first();

            if (!$friendship) {
                return response()->json([
                    'success' => false,
                    'message' => 'Users are not friends',
                ], 404);
            }

            $mutualFriends = $friendship->getMutualFriends();

            return response()->json([
                'success' => true,
                'data' => [
                                         'mutual_friends' => $mutualFriends->map(function ($user) {
                         return [
                             'id' => $user->id,
                             'name' => $user->name,
                             'username' => $user->username,
                             'avatar_url' => $user->profile->avatar_url ?? null,
                         ];
                     }),
                    'count' => $mutualFriends->count(),
                    'friendship_duration' => $friendship->friendship_duration,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch mutual friends',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get friendship statistics for current user.
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = Friendship::getStatsForUser($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'statistics' => $stats,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch friendship statistics',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get friend suggestions for current user.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $request->validate([
            'count' => 'sometimes|integer|min:1|max:50',
            'algorithm' => 'sometimes|string|in:mutual_connections,friends_of_friends,network_analysis,hybrid',
            'include_scores' => 'sometimes|boolean',
            'min_score' => 'sometimes|numeric|min:0|max:1',
            'use_cache' => 'sometimes|boolean',
            'min_mutual_friends' => 'sometimes|integer|min:0|max:20',
        ]);

        try {
            $user = Auth::user();
            
            $options = [
                'count' => $request->input('count', 10),
                'algorithm' => $request->input('algorithm', 'mutual_connections'),
                'include_scores' => $request->input('include_scores', false),
                'min_score' => $request->input('min_score', 0.1),
                'use_cache' => $request->input('use_cache', true),
                'min_mutual_friends' => $request->input('min_mutual_friends', 1),
            ];

            $suggestions = $this->friendSuggestionService->getSuggestions($user, $options);

            return response()->json([
                'success' => true,
                'data' => [
                    'suggestions' => $suggestions->toArray(),
                    'algorithm' => $options['algorithm'],
                    'count' => $suggestions->count(),
                    'options' => $options,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch friend suggestions',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get suggestion analytics for current user.
     */
    public function suggestionAnalytics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $analytics = $this->friendSuggestionService->getSuggestionAnalytics($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'analytics' => $analytics,
                    'generated_at' => now()->toISOString(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch suggestion analytics',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Clear suggestion cache for current user.
     */
    public function clearSuggestionCache(): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->friendSuggestionService->clearUserCache($user);

            return response()->json([
                'success' => true,
                'message' => 'Suggestion cache cleared successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear suggestion cache',
                'error' => $e->getMessage(),
            ], 422);
        }
    }


} 