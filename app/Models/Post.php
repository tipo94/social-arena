<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'group_id',
        'content',
        'type',
        'metadata',
        'visibility',
        'custom_audience',
        'allow_resharing',
        'allow_comments',
        'allow_reactions',
        'visibility_expires_at',
        'visibility_history',
        'visibility_changed_at',
        'likes_count',
        'comments_count',
        'shares_count',
        'views_count',
        'reach_count',
        'is_reported',
        'is_hidden',
        'moderated_at',
        'moderated_by',
        'published_at',
        'is_scheduled',
        'edit_history',
        'last_edited_at',
        'last_edited_by',
        'edit_count',
        'is_edited',
        'allow_editing',
        'editing_locked_at',
        'edit_deadline',
        'deletion_reason',
        'deleted_by',
        'deletion_scheduled_at',
        'permanent_deletion_at',
        'can_be_restored',
        'current_version',
        'original_content',
        'edit_notifications_sent',
        'notification_recipients',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'custom_audience' => 'array',
        'visibility_history' => 'array',
        'edit_history' => 'array',
        'original_content' => 'array',
        'notification_recipients' => 'array',
        'allow_resharing' => 'boolean',
        'allow_comments' => 'boolean',
        'allow_reactions' => 'boolean',
        'allow_editing' => 'boolean',
        'is_edited' => 'boolean',
        'can_be_restored' => 'boolean',
        'edit_notifications_sent' => 'boolean',
        'is_reported' => 'boolean',
        'is_hidden' => 'boolean',
        'is_scheduled' => 'boolean',
        'moderated_at' => 'datetime',
        'published_at' => 'datetime',
        'visibility_expires_at' => 'datetime',
        'visibility_changed_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'editing_locked_at' => 'datetime',
        'edit_deadline' => 'datetime',
        'deletion_scheduled_at' => 'datetime',
        'permanent_deletion_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'excerpt',
        'reading_time',
        'has_media',
        'media_count',
    ];

    /**
     * Get the user that owns the post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the group that the post belongs to.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Get the comments for the post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Get all of the post's likes.
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Get all of the post's media attachments.
     */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(MediaAttachment::class, 'attachable');
    }

    /**
     * Get all revisions for this post.
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->orderBy('version_number', 'desc');
    }

    /**
     * Get deletion logs for this post.
     */
    public function deletionLogs(): HasMany
    {
        return $this->hasMany(PostDeletionLog::class);
    }

    /**
     * Get the user who last edited this post.
     */
    public function lastEditedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    /**
     * Get the user who deleted this post.
     */
    public function deletedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * Get ready media attachments only.
     */
    public function readyMedia(): MorphMany
    {
        return $this->mediaAttachments()->ready();
    }

    /**
     * Get image attachments only.
     */
    public function images(): MorphMany
    {
        return $this->mediaAttachments()->images()->ready();
    }

    /**
     * Get video attachments only.
     */
    public function videos(): MorphMany
    {
        return $this->mediaAttachments()->videos()->ready();
    }

    /**
     * Get document attachments only.
     */
    public function documents(): MorphMany
    {
        return $this->mediaAttachments()->whereIn('type', ['document', 'archive'])->ready();
    }

    /**
     * Get the first image attachment.
     */
    public function firstImage(): ?MediaAttachment
    {
        return $this->images()->first();
    }

    /**
     * Get post excerpt for previews.
     */
    public function getExcerptAttribute(): string
    {
        if (!$this->content) {
            return '';
        }

        return Str::limit(strip_tags($this->content), 150);
    }

    /**
     * Get estimated reading time in minutes.
     */
    public function getReadingTimeAttribute(): int
    {
        if (!$this->content) {
            return 1;
        }

        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, ceil($wordCount / 200)); // Average reading speed: 200 words per minute
    }

    /**
     * Check if post has media attachments.
     */
    public function getHasMediaAttribute(): bool
    {
        return $this->readyMedia()->exists();
    }

    /**
     * Get count of media attachments.
     */
    public function getMediaCountAttribute(): int
    {
        return $this->readyMedia()->count();
    }

    /**
     * Check if the post is published.
     */
    public function isPublished(): bool
    {
        if ($this->is_scheduled) {
            return $this->published_at && $this->published_at->isPast();
        }

        return !$this->is_hidden && !$this->is_reported;
    }

    /**
     * Check if the post is scheduled for future publishing.
     */
    public function isScheduled(): bool
    {
        return $this->is_scheduled && $this->published_at && $this->published_at->isFuture();
    }

    /**
     * Check if the post is visible to a specific user.
     */
    public function isVisibleTo(?User $user = null): bool
    {
        // Hidden or reported posts are not visible
        if ($this->is_hidden || $this->is_reported) {
            return false;
        }

        // Scheduled posts are only visible if published
        if ($this->is_scheduled && !$this->isPublished()) {
            return false;
        }

        // Post owner can always see their own posts
        if ($user && $user->id === $this->user_id) {
            return true;
        }

        // Check if post visibility has expired
        if ($this->visibility_expires_at && $this->visibility_expires_at->isPast()) {
            return false;
        }

        // Use ContentVisibilityService for comprehensive visibility checking
        $visibilityService = app(\App\Services\ContentVisibilityService::class);
        return $visibilityService->isPostVisibleTo($this, $user);
    }

    /**
     * Check if a user can edit this post.
     */
    public function canEditBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Post owner can edit
        if ($user->id === $this->user_id) {
            return true;
        }

        // Admins can edit any post
        if ($user->isAdmin()) {
            return true;
        }

        // Group moderators can edit posts in their groups
        if ($this->group && $this->group->memberships()->where('user_id', $user->id)->whereIn('role', ['admin', 'moderator'])->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Increment the likes count.
     */
    public function incrementLikes(): void
    {
        $this->increment('likes_count');
    }

    /**
     * Decrement the likes count.
     */
    public function decrementLikes(): void
    {
        if ($this->likes_count > 0) {
            $this->decrement('likes_count');
        }
    }

    /**
     * Increment the comments count.
     */
    public function incrementComments(): void
    {
        $this->increment('comments_count');
    }

    /**
     * Decrement the comments count.
     */
    public function decrementComments(): void
    {
        if ($this->comments_count > 0) {
            $this->decrement('comments_count');
        }
    }

    /**
     * Increment the shares count.
     */
    public function incrementShares(): void
    {
        $this->increment('shares_count');
    }

    /**
     * Increment the views count.
     */
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    /**
     * Increment the reach count.
     */
    public function incrementReach(): void
    {
        $this->increment('reach_count');
    }

    /**
     * Update post visibility with history tracking.
     */
    public function updateVisibility(string $newVisibility, ?User $user = null, array $customAudience = []): bool
    {
        $oldVisibility = $this->visibility;
        
        // Track visibility change in history
        $historyEntry = [
            'from' => $oldVisibility,
            'to' => $newVisibility,
            'changed_by' => $user?->id,
            'changed_at' => now()->toISOString(),
            'custom_audience' => $customAudience,
        ];

        $history = $this->visibility_history ?? [];
        $history[] = $historyEntry;

        $this->update([
            'visibility' => $newVisibility,
            'custom_audience' => $newVisibility === 'custom' ? $customAudience : null,
            'visibility_history' => $history,
            'visibility_changed_at' => now(),
        ]);

        return true;
    }

    /**
     * Check if post allows specific interaction.
     */
    public function allowsInteraction(string $interactionType): bool
    {
        return match ($interactionType) {
            'comment' => $this->allow_comments,
            'reaction', 'like' => $this->allow_reactions,
            'share', 'reshare' => $this->allow_resharing,
            default => false,
        };
    }

    /**
     * Check if post is temporary (has expiring visibility).
     */
    public function isTemporary(): bool
    {
        return $this->visibility_expires_at !== null;
    }

    /**
     * Check if post visibility has expired.
     */
    public function hasExpiredVisibility(): bool
    {
        return $this->isTemporary() && $this->visibility_expires_at->isPast();
    }

    /**
     * Get audience summary for this post.
     */
    public function getAudienceSummary(): array
    {
        $visibilityService = app(\App\Services\ContentVisibilityService::class);
        return $visibilityService->getContentAudience($this);
    }

    /**
     * Scope for posts that allow specific interactions.
     */
    public function scopeAllowingInteraction(Builder $query, string $interactionType): Builder
    {
        $column = match ($interactionType) {
            'comment' => 'allow_comments',
            'reaction', 'like' => 'allow_reactions',
            'share', 'reshare' => 'allow_resharing',
            default => null,
        };

        if (!$column) {
            return $query->whereRaw('0 = 1'); // Return no results for invalid interaction type
        }

        return $query->where($column, true);
    }

    /**
     * Scope for posts with specific visibility.
     */
    public function scopeWithVisibility(Builder $query, string|array $visibility): Builder
    {
        if (is_array($visibility)) {
            return $query->whereIn('visibility', $visibility);
        }
        
        return $query->where('visibility', $visibility);
    }

    /**
     * Scope for temporary posts (with expiring visibility).
     */
    public function scopeTemporary(Builder $query): Builder
    {
        return $query->whereNotNull('visibility_expires_at');
    }

    /**
     * Scope for posts where visibility has expired.
     */
    public function scopeExpiredVisibility(Builder $query): Builder
    {
        return $query->whereNotNull('visibility_expires_at')
                    ->where('visibility_expires_at', '<', now());
    }

    /**
     * Scope for posts that are shareable.
     */
    public function scopeShareable(Builder $query): Builder
    {
        return $query->where('allow_resharing', true);
    }

    /**
     * Scope for posts that allow comments.
     */
    public function scopeCommentable(Builder $query): Builder
    {
        return $query->where('allow_comments', true);
    }

    /**
     * Scope for edited posts.
     */
    public function scopeEdited(Builder $query): Builder
    {
        return $query->where('is_edited', true);
    }

    /**
     * Scope for recently edited posts.
     */
    public function scopeRecentlyEdited(Builder $query, int $hours = 24): Builder
    {
        return $query->where('is_edited', true)
                    ->where('last_edited_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for locked posts.
     */
    public function scopeLocked(Builder $query): Builder
    {
        return $query->whereNotNull('editing_locked_at');
    }

    /**
     * Scope for posts with edit deadlines.
     */
    public function scopeWithEditDeadlines(Builder $query): Builder
    {
        return $query->whereNotNull('edit_deadline');
    }

    /**
     * Check if post is currently editable.
     */
    public function isCurrentlyEditable(): bool
    {
        if (!$this->allow_editing || $this->editing_locked_at) {
            return false;
        }

        if ($this->edit_deadline && now() > $this->edit_deadline) {
            return false;
        }

        return true;
    }

    /**
     * Get edit status information.
     */
    public function getEditStatus(): array
    {
        return [
            'is_edited' => $this->is_edited,
            'edit_count' => $this->edit_count,
            'current_version' => $this->current_version,
            'last_edited_at' => $this->last_edited_at?->toISOString(),
            'is_locked' => $this->editing_locked_at !== null,
            'is_editable' => $this->isCurrentlyEditable(),
            'edit_deadline' => $this->edit_deadline?->toISOString(),
        ];
    }

    /**
     * Get edit summary for display.
     */
    public function getEditSummary(): string
    {
        if (!$this->is_edited) {
            return '';
        }

        $lastEdit = collect($this->edit_history)->last();
        if (!$lastEdit) {
            return 'Post has been edited';
        }

        $changeTypes = $lastEdit['changes_summary'] ?? [];
        $changeCount = count($changeTypes);

        if ($changeCount === 1 && in_array('content', $changeTypes)) {
            return 'Content edited';
        }

        if ($changeCount === 1) {
            return ucfirst($changeTypes[0]) . ' edited';
        }

        return "{$changeCount} changes made";
    }

    /**
     * Scope for published posts only.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_hidden', false)
                    ->where('is_reported', false)
                    ->where(function ($q) {
                        $q->where('is_scheduled', false)
                          ->orWhere(function ($sq) {
                              $sq->where('is_scheduled', true)
                                 ->where('published_at', '<=', now());
                          });
                    });
    }

    /**
     * Scope for posts visible to a specific user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $query->published();

        if (!$user) {
            return $query->where('visibility', 'public');
        }

        // Use ContentVisibilityService for more comprehensive filtering
        $visibilityService = app(\App\Services\ContentVisibilityService::class);
        return $visibilityService->applyPostVisibilityFilter($query, $user);
    }

    /**
     * Scope for posts with media.
     */
    public function scopeWithMedia(Builder $query): Builder
    {
        return $query->whereHas('mediaAttachments', function ($q) {
            $q->ready();
        });
    }

    /**
     * Scope for posts by type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope for posts from a specific timeframe.
     */
    public function scopeFromPeriod(Builder $query, string $period): Builder
    {
        return match ($period) {
            'today' => $query->whereDate('created_at', today()),
            'week' => $query->where('created_at', '>=', now()->subWeek()),
            'month' => $query->where('created_at', '>=', now()->subMonth()),
            'year' => $query->where('created_at', '>=', now()->subYear()),
            default => $query,
        };
    }

    /**
     * Scope for popular posts (high engagement).
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderByRaw('(likes_count + comments_count + shares_count) DESC');
    }

    /**
     * Scope for trending posts (recent + popular).
     */
    public function scopeTrending(Builder $query): Builder
    {
        return $query->where('created_at', '>=', now()->subDays(7))
                    ->orderByRaw('((likes_count + comments_count + shares_count) / GREATEST(DATEDIFF(NOW(), created_at), 1)) DESC');
    }

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        // Set default published_at when creating
        static::creating(function (Post $post) {
            if (!$post->published_at && !$post->is_scheduled) {
                $post->published_at = now();
            }
        });

        // Update user's post count when post is created/deleted
        static::created(function (Post $post) {
            $post->user->profile?->incrementCounter('posts_count');
            
            if ($post->group) {
                $post->group->increment('posts_count');
                $post->group->update(['last_post_at' => now()]);
            }
        });

        static::deleted(function (Post $post) {
            $post->user->profile?->decrementCounter('posts_count');
            
            if ($post->group) {
                $post->group->decrement('posts_count');
            }
        });
    }
}
