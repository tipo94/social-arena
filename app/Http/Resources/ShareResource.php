<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ShareResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'username' => $this->user->username,
                'avatar_url' => $this->user->avatar_url,
            ],
            'share_type' => $this->share_type,
            'platform' => $this->platform,
            'content' => $this->content,
            'visibility' => $this->visibility,
            
            // Share flags
            'is_quote_share' => $this->is_quote_share,
            'is_private_share' => $this->is_private_share,
            'is_repost' => $this->is_repost,
            'is_external_share' => $this->is_external_share,
            
            // Shared to (if applicable)
            'shared_to_user' => $this->when($this->sharedToUser, function () {
                return [
                    'id' => $this->sharedToUser->id,
                    'name' => $this->sharedToUser->name,
                    'username' => $this->sharedToUser->username,
                ];
            }),
            'shared_to_group' => $this->when($this->sharedToGroup, function () {
                return [
                    'id' => $this->sharedToGroup->id,
                    'name' => $this->sharedToGroup->name,
                    'slug' => $this->sharedToGroup->slug,
                ];
            }),
            
            // Original content (when loaded)
            'shareable' => $this->when($this->relationLoaded('shareable'), function () {
                switch ($this->shareable_type) {
                    case \App\Models\Post::class:
                        return new PostResource($this->shareable);
                    case \App\Models\Comment::class:
                        return new CommentResource($this->shareable);
                    default:
                        return null;
                }
            }),
            
            // URLs
            'share_url' => $this->when($this->is_external_share, function () {
                return $this->getShareUrl();
            }),
            
            // User permissions
            'can_edit' => $this->when(Auth::check(), function () {
                return Auth::id() === $this->user_id;
            }),
            'can_delete' => $this->when(Auth::check(), function () {
                $user = Auth::user();
                return Auth::id() === $this->user_id || 
                       (method_exists($user, 'isAdmin') && $user->isAdmin());
            }),
            
            // Metadata
            'metadata' => $this->when($this->metadata, $this->metadata),
            
            // Timestamps
            'shared_at' => $this->shared_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
} 