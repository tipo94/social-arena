<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class PostResource extends JsonResource
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
            'group' => $this->when($this->group, function () {
                return [
                    'id' => $this->group->id,
                    'name' => $this->group->name,
                    'slug' => $this->group->slug,
                    'icon' => $this->group->icon,
                ];
            }),
            'content' => $this->content,
            'content_plain' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->toPlainText($this->content);
            }),
            'type' => $this->type,
            'visibility' => $this->visibility,
            'metadata' => $this->when($this->metadata, $this->metadata),
            
            // Engagement metrics
            'likes_count' => $this->likes_count,
            'comments_count' => $this->comments_count,
            'shares_count' => $this->shares_count,
            'views_count' => $this->when(isset($this->views_count), $this->views_count),
            
            // User's interaction status
            'is_liked_by_user' => $this->when(Auth::check(), function () {
                return $this->likes()->where('user_id', Auth::id())->exists();
            }),
            
            // Media attachments
            'media' => MediaAttachmentResource::collection($this->whenLoaded('mediaAttachments')),
            'has_media' => $this->has_media,
            'media_count' => $this->media_count,
            
            // Comments (preview or full based on load)
            'comments' => $this->when($this->relationLoaded('comments'), function () {
                if ($this->comments->count() <= 3) {
                    return CommentResource::collection($this->comments);
                }
                // Return only top 3 comments for preview
                return CommentResource::collection($this->comments->take(3));
            }),
            
            // Computed attributes
            'excerpt' => $this->excerpt,
            'reading_time' => $this->reading_time,
            'word_count' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->wordCount($this->content);
            }),
            
            // Post status
            'is_published' => $this->isPublished(),
            'is_scheduled' => $this->isScheduled(),
            'is_reported' => $this->is_reported,
            'is_hidden' => $this->is_hidden,
            
            // User permissions
            'can_edit' => $this->when(Auth::check(), function () {
                return $this->canEditBy(Auth::user());
            }),
            'can_delete' => $this->when(Auth::check(), function () {
                return $this->canEditBy(Auth::user());
            }),
            
            // Timestamps
            'published_at' => $this->when($this->published_at, function () {
                return $this->published_at?->toISOString();
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Extracted content elements (for frontend processing)
            'mentions' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->extractMentions($this->content);
            }),
            'hashtags' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->extractHashtags($this->content);
            }),
            'urls' => $this->when($this->content, function () {
                return app(\App\Services\TextFormattingService::class)->extractUrls($this->content);
            }),
            
            // Additional metadata for different post types
            'book_info' => $this->when($this->type === 'book_review' && $this->metadata, function () {
                return [
                    'title' => $this->metadata['book_title'] ?? null,
                    'author' => $this->metadata['book_author'] ?? null,
                    'isbn' => $this->metadata['book_isbn'] ?? null,
                    'rating' => $this->metadata['book_rating'] ?? null,
                    'review' => $this->metadata['book_review'] ?? null,
                ];
            }),
            
            'link_info' => $this->when($this->type === 'link' && $this->metadata, function () {
                return [
                    'url' => $this->metadata['link_url'] ?? null,
                    'title' => $this->metadata['link_title'] ?? null,
                    'description' => $this->metadata['link_description'] ?? null,
                ];
            }),
            
            'poll_info' => $this->when($this->type === 'poll' && $this->metadata, function () {
                return [
                    'question' => $this->metadata['poll_question'] ?? null,
                    'options' => $this->metadata['poll_options'] ?? [],
                    'expires_at' => isset($this->metadata['poll_expires_at']) 
                        ? \Carbon\Carbon::parse($this->metadata['poll_expires_at'])->toISOString()
                        : null,
                    'total_votes' => 0, // This would be calculated from poll votes table
                    'user_vote' => null, // This would be fetched from poll votes table
                ];
            }),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    /**
     * Customize the response for a request.
     */
    public function withResponse(Request $request, $response): void
    {
        // Add any custom headers or modify response as needed
        $response->header('X-Resource-Type', 'Post');
    }
} 