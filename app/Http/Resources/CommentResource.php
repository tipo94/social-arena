<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class CommentResource extends JsonResource
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
                'is_verified' => $this->when($this->user->profile, function () {
                    return $this->user->profile->is_verified;
                }),
            ],
            'post_id' => $this->post_id,
            'parent_id' => $this->parent_id,
            'content' => $this->content,
            'content_plain' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->toPlainText($this->content);
            }),
            'type' => $this->type,
            
            // Engagement metrics
            'likes_count' => $this->likes_count,
            'replies_count' => $this->replies_count,
            
            // User's interaction status
            'is_liked_by_user' => $this->when(Auth::check(), function () {
                return $this->likes()->where('user_id', Auth::id())->exists();
            }),
            
            // Nested comments structure
            'depth' => $this->depth,
            'path' => $this->path,
            
            // Replies (if loaded)
            'replies' => CommentResource::collection($this->whenLoaded('replies')),
            
            // User permissions
            'can_edit' => $this->when(Auth::check(), function () {
                $user = Auth::user();
                return Auth::id() === $this->user_id || ($user && $user->isAdmin());
            }),
            'can_delete' => $this->when(Auth::check(), function () {
                $user = Auth::user();
                return Auth::id() === $this->user_id || ($user && $user->isAdmin());
            }),
            
            // Status
            'is_reported' => $this->is_reported,
            'is_hidden' => $this->is_hidden,
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Extracted content elements
            'mentions' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->extractMentions($this->content);
            }),
            'hashtags' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->extractHashtags($this->content);
            }),
        ];
    }
} 