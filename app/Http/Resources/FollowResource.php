<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class FollowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $currentUser = Auth::user();
        
        return [
            'id' => $this->id,
            'followed_at' => $this->followed_at?->toISOString(),
            
            // Follow settings
            'is_muted' => $this->is_muted,
            'show_notifications' => $this->show_notifications,
            'is_close_friend' => $this->is_close_friend,
            'interaction_preferences' => $this->interaction_preferences,
            
            // Follower information (when showing followers)
            'follower' => $this->when($this->follower, function () {
                return [
                    'id' => $this->follower->id,
                    'name' => $this->follower->name,
                    'username' => $this->follower->username,
                    'avatar_url' => $this->follower->profile->avatar_url ?? null,
                    'is_verified' => $this->follower->profile->is_verified ?? false,
                    'is_online' => $this->follower->is_online ?? false,
                    'last_activity_at' => $this->follower->last_activity_at?->toISOString(),
                ];
            }),
            
            // Following information (when showing following)
            'following' => $this->when($this->following, function () {
                return [
                    'id' => $this->following->id,
                    'name' => $this->following->name,
                    'username' => $this->following->username,
                    'avatar_url' => $this->following->profile->avatar_url ?? null,
                    'is_verified' => $this->following->profile->is_verified ?? false,
                    'is_online' => $this->following->is_online ?? false,
                    'last_activity_at' => $this->following->last_activity_at?->toISOString(),
                ];
            }),
            
            // User capabilities for the current user
            'can_modify' => $this->when(Auth::check(), function () use ($currentUser) {
                return $this->canBeModifiedBy($currentUser);
            }),
            
            // Additional metadata
            'is_active' => $this->isActive(),
            'follow_duration_days' => $this->followed_at?->diffInDays(now()),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get additional data for the resource.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'resource_type' => 'follow',
                'generated_at' => now()->toISOString(),
            ],
        ];
    }
} 