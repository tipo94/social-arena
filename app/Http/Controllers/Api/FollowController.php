<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Follow;
use App\Http\Resources\FollowResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FollowController extends Controller
{
    /**
     * Get current user's following list.
     */
    public function following(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'include_muted' => 'sometimes|boolean',
            'close_friends_only' => 'sometimes|boolean',
            'sort' => 'sometimes|string|in:name,recent,activity',
            'order' => 'sometimes|string|in:asc,desc',
        ]);

        try {
            $user = Auth::user();
            $search = $request->input('search');
            $perPage = $request->input('per_page', 20);
            $includeMuted = $request->input('include_muted', false);
            $closeFriendsOnly = $request->input('close_friends_only', false);
            $sort = $request->input('sort', 'recent');
            $order = $request->input('order', 'desc');

            $query = $user->following()
                          ->with(['following.profile']);

            // Filter by muted status
            if (!$includeMuted) {
                $query->active();
            }

            // Filter by close friends
            if ($closeFriendsOnly) {
                $query->closeFriends();
            }

            // Apply search filter
            if ($search) {
                $query->whereHas('following', function ($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%")
                          ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            switch ($sort) {
                case 'name':
                    $query->join('users as u', 'follows.following_id', '=', 'u.id')
                          ->orderBy('u.name', $order)
                          ->select('follows.*');
                    break;
                case 'activity':
                    $query->join('users as u', 'follows.following_id', '=', 'u.id')
                          ->orderBy('u.last_activity_at', $order)
                          ->select('follows.*');
                    break;
                case 'recent':
                default:
                    $query->orderBy('followed_at', $order);
                    break;
            }

            $follows = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => FollowResource::collection($follows->items()),
                'pagination' => [
                    'current_page' => $follows->currentPage(),
                    'per_page' => $follows->perPage(),
                    'total' => $follows->total(),
                    'last_page' => $follows->lastPage(),
                    'has_more_pages' => $follows->hasMorePages(),
                ],
                'filters' => [
                    'search' => $search,
                    'include_muted' => $includeMuted,
                    'close_friends_only' => $closeFriendsOnly,
                    'sort' => $sort,
                    'order' => $order,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch following list',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get current user's followers list.
     */
    public function followers(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'sometimes|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sort' => 'sometimes|string|in:name,recent,activity',
            'order' => 'sometimes|string|in:asc,desc',
        ]);

        try {
            $user = Auth::user();
            $search = $request->input('search');
            $perPage = $request->input('per_page', 20);
            $sort = $request->input('sort', 'recent');
            $order = $request->input('order', 'desc');

            $query = $user->followers()
                          ->with(['follower.profile']);

            // Apply search filter
            if ($search) {
                $query->whereHas('follower', function ($userQ) use ($search) {
                    $userQ->where('name', 'like', "%{$search}%")
                          ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Apply sorting
            switch ($sort) {
                case 'name':
                    $query->join('users as u', 'follows.follower_id', '=', 'u.id')
                          ->orderBy('u.name', $order)
                          ->select('follows.*');
                    break;
                case 'activity':
                    $query->join('users as u', 'follows.follower_id', '=', 'u.id')
                          ->orderBy('u.last_activity_at', $order)
                          ->select('follows.*');
                    break;
                case 'recent':
                default:
                    $query->orderBy('followed_at', $order);
                    break;
            }

            $follows = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => FollowResource::collection($follows->items()),
                'pagination' => [
                    'current_page' => $follows->currentPage(),
                    'per_page' => $follows->perPage(),
                    'total' => $follows->total(),
                    'last_page' => $follows->lastPage(),
                    'has_more_pages' => $follows->hasMorePages(),
                ],
                'filters' => [
                    'search' => $search,
                    'sort' => $sort,
                    'order' => $order,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch followers list',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Follow a user.
     */
    public function follow(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'is_close_friend' => 'sometimes|boolean',
            'show_notifications' => 'sometimes|boolean',
        ]);

        try {
            $currentUser = Auth::user();
            $targetUserId = $request->input('user_id');
            $targetUser = User::findOrFail($targetUserId);

            // Prevent self-following
            if ($currentUser->id === $targetUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow yourself',
                ], 422);
            }

            // Check if already following
            if ($currentUser->isFollowing($targetUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are already following this user',
                ], 422);
            }

            // Check privacy settings
            if (!$this->canFollow($currentUser, $targetUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow this user due to their privacy settings',
                ], 403);
            }

            $options = [
                'is_close_friend' => $request->input('is_close_friend', false),
                'show_notifications' => $request->input('show_notifications', true),
            ];

            $follow = $currentUser->follow($targetUser, $options);

            return response()->json([
                'success' => true,
                'message' => 'Successfully followed user',
                'data' => new FollowResource($follow),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to follow user',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Unfollow a user.
     */
    public function unfollow(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $currentUser = Auth::user();
            $targetUserId = $request->input('user_id');
            $targetUser = User::findOrFail($targetUserId);

            // Check if following
            if (!$currentUser->isFollowing($targetUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not following this user',
                ], 422);
            }

            $success = $currentUser->unfollow($targetUser);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Successfully unfollowed user',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to unfollow user',
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to unfollow user',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle follow status for a user.
     */
    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        try {
            $currentUser = Auth::user();
            $targetUserId = $request->input('user_id');
            $targetUser = User::findOrFail($targetUserId);

            // Prevent self-following
            if ($currentUser->id === $targetUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot follow yourself',
                ], 422);
            }

            $isFollowing = $currentUser->isFollowing($targetUser);

            if ($isFollowing) {
                $success = $currentUser->unfollow($targetUser);
                $action = 'unfollowed';
            } else {
                // Check privacy settings before following
                if (!$this->canFollow($currentUser, $targetUser)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You cannot follow this user due to their privacy settings',
                    ], 403);
                }

                $follow = $currentUser->follow($targetUser);
                $success = $follow !== null;
                $action = 'followed';
            }

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Successfully {$action} user",
                    'data' => [
                        'is_following' => !$isFollowing,
                        'action' => $action,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => "Failed to {$action} user",
                ], 422);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle follow status',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get follow statistics for the current user.
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            $stats = Follow::getStatsForUser($user);

            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch follow statistics',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update follow settings (mute, notifications, close friend status).
     */
    public function updateSettings(Request $request, Follow $follow): JsonResponse
    {
        $request->validate([
            'is_muted' => 'sometimes|boolean',
            'show_notifications' => 'sometimes|boolean',
            'is_close_friend' => 'sometimes|boolean',
        ]);

        try {
            $currentUser = Auth::user();

            // Check if user can modify this follow
            if (!$follow->canBeModifiedBy($currentUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You cannot modify this follow relationship',
                ], 403);
            }

            $follow->update($request->only([
                'is_muted',
                'show_notifications',
                'is_close_friend',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Follow settings updated successfully',
                'data' => new FollowResource($follow->fresh()),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update follow settings',
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Check if a user can follow another user based on privacy settings.
     */
    protected function canFollow(User $follower, User $following): bool
    {
        // Can't follow banned or inactive users
        if ($following->is_banned || !$following->is_active) {
            return false;
        }

        // Private profiles might have restrictions
        if ($following->profile && $following->profile->is_private_profile) {
            // For now, allow following private profiles
            // In the future, this could require approval
            return true;
        }

        return true;
    }
} 