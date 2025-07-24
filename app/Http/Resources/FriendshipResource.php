<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class FriendshipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $currentUser = Auth::user();
        $otherUser = $this->getOtherUser($currentUser);

        return [
            'id' => $this->id,
            'status' => $this->status,
            
            // Status flags
            'is_pending' => $this->is_pending,
            'is_accepted' => $this->is_accepted,
            'is_blocked' => $this->is_blocked,
            
            // User information
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar_url' => $this->user->profile->avatar_url ?? null,
                'is_verified' => $this->user->profile->is_verified ?? false,
            ],
            'friend' => [
                'id' => $this->friend->id,
                'name' => $this->friend->name,
                'username' => $this->friend->username,
                'avatar_url' => $this->friend->profile->avatar_url ?? null,
                'is_verified' => $this->friend->profile->is_verified ?? false,
            ],
            
            // The other user (for easier frontend handling)
            'other_user' => $this->when($otherUser, function () use ($otherUser) {
                return [
                    'id' => $otherUser->id,
                    'name' => $otherUser->name,
                    'username' => $otherUser->username,
                    'avatar_url' => $otherUser->profile->avatar_url ?? null,
                    'is_verified' => $otherUser->profile->is_verified ?? false,
                    'is_online' => $otherUser->is_online ?? false,
                    'last_activity_at' => $otherUser->last_activity_at?->toISOString(),
                ];
            }),
            
            // Relationship metadata
            'mutual_friends_count' => $this->when($this->is_accepted, $this->mutual_friends_count),
            'friendship_duration' => $this->when($this->is_accepted, $this->friendship_duration),
            
            // Permission settings
            'permissions' => [
                'can_see_posts' => $this->can_see_posts,
                'can_send_messages' => $this->can_send_messages,
                'show_in_friends_list' => $this->show_in_friends_list,
            ],
            
            // User capabilities
            'can_accept' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->status === 'pending' && $this->friend_id === $currentUser->id;
            }),
            'can_decline' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->status === 'pending' && $this->friend_id === $currentUser->id;
            }),
            'can_block' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->canBeModifiedBy($currentUser);
            }),
            'can_unblock' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->status === 'blocked' && $this->canBeModifiedBy($currentUser);
            }),
            'can_remove' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->status === 'accepted' && $this->canBeModifiedBy($currentUser);
            }),
            
            // Timestamps
            'requested_at' => $this->requested_at?->toISOString(),
            'accepted_at' => $this->when($this->accepted_at, $this->accepted_at?->toISOString()),
            'blocked_at' => $this->when($this->blocked_at, $this->blocked_at?->toISOString()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Additional metadata for analytics
            'metadata' => $this->when($this->is_accepted && Auth::check(), function () use ($currentUser) {
                return [
                    'initiated_by_current_user' => $this->user_id === $currentUser->id,
                    'days_since_request' => $this->requested_at->diffInDays(now()),
                    'days_since_acceptance' => $this->accepted_at?->diffInDays(now()),
                ];
            }),
        ];
    }

    /**
     * Get additional data for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_type' => 'friendship',
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
} 