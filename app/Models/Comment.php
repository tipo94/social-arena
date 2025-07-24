<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Maximum nesting depth allowed for replies.
     */
    const MAX_DEPTH = 5;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'post_id',
        'parent_id',
        'content',
        'type',
        'likes_count',
        'replies_count',
        'depth',
        'path',
        'is_reported',
        'is_hidden',
        'moderated_at',
        'moderated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_reported' => 'boolean',
        'is_hidden' => 'boolean',
        'moderated_at' => 'datetime',
        'depth' => 'integer',
        'likes_count' => 'integer',
        'replies_count' => 'integer',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_root',
        'has_replies',
        'can_reply',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Comment $comment) {
            $comment->setDepthAndPath();
        });

        static::created(function (Comment $comment) {
            $comment->updateParentRepliesCount();
            $comment->post->incrementComments();
            $comment->updatePath();
        });

        static::deleted(function (Comment $comment) {
            $comment->updateParentRepliesCount(-1);
            $comment->post->decrementComments();
        });
    }

    /**
     * Get the user that owns the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the post that the comment belongs to.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Get the parent comment.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Get the replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
                   ->orderBy('created_at', 'asc');
    }

    /**
     * Get all replies recursively with proper ordering.
     */
    public function allReplies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')
                   ->with('allReplies.user')
                   ->orderBy('created_at', 'asc');
    }

    /**
     * Get all of the comment's likes.
     */
    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    /**
     * Get all descendants of this comment.
     */
    public function descendants(): Builder
    {
        return static::where('path', 'like', $this->path . '.%')
                    ->orderBy('path');
    }

    /**
     * Get all ancestors of this comment.
     */
    public function ancestors(): Collection
    {
        if ($this->isRoot()) {
            return collect();
        }

        // Get ancestors by traversing up the parent chain
        $ancestors = collect();
        $current = $this;
        
        while ($current->parent_id) {
            $current = $current->parent;
            $ancestors->prepend($current);
        }
        
        return $ancestors;
    }

    /**
     * Get the root comment of this thread.
     */
    public function root(): ?Comment
    {
        if ($this->isRoot()) {
            return $this;
        }

        $pathParts = explode('.', $this->path);
        $rootId = $pathParts[0];

        return static::find($rootId);
    }

    /**
     * Get all siblings of this comment.
     */
    public function siblings(): Builder
    {
        return static::where('parent_id', $this->parent_id)
                    ->where('id', '!=', $this->id);
    }

    /**
     * Set depth and materialized path based on parent.
     */
    protected function setDepthAndPath(): void
    {
        if (!$this->parent_id) {
            // Root comment
            $this->depth = 0;
            $this->path = null; // Will be set after creation with the comment ID
        } else {
            $parent = $this->parent ?? Comment::find($this->parent_id);
            
            if (!$parent) {
                throw new \InvalidArgumentException('Parent comment not found');
            }

            if ($parent->depth >= self::MAX_DEPTH) {
                throw new \InvalidArgumentException('Maximum nesting depth exceeded');
            }

            $this->depth = $parent->depth + 1;
            
            // Build path: parent's path + parent's ID
            $parentPath = $parent->path ?: $parent->id;
            $this->path = $parentPath . '.' . ($parent->replies_count + 1);
        }
    }

    /**
     * Update the materialized path after creation.
     */
    public function updatePath(): void
    {
        if ($this->depth === 0) {
            // Root comment - path is just the ID
            $this->update(['path' => (string) $this->id]);
        }
    }

    /**
     * Update parent's replies count.
     */
    protected function updateParentRepliesCount(int $increment = 1): void
    {
        if ($this->parent_id) {
            $parent = $this->parent ?? Comment::find($this->parent_id);
            if ($parent) {
                if ($increment > 0) {
                    $parent->increment('replies_count', $increment);
                } else {
                    $parent->decrement('replies_count', abs($increment));
                }
            }
        }
    }

    /**
     * Check if this is a root comment.
     */
    public function isRoot(): bool
    {
        return $this->depth === 0 || is_null($this->parent_id);
    }

    /**
     * Check if this comment has replies.
     */
    public function hasReplies(): bool
    {
        return $this->replies_count > 0;
    }

    /**
     * Check if replying to this comment is allowed.
     */
    public function canReply(): bool
    {
        return $this->depth < self::MAX_DEPTH && 
               !$this->is_hidden && 
               $this->post && 
               $this->post->allow_comments;
    }

    /**
     * Check if a user can edit this comment.
     */
    public function canEditBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Comment owner can edit within 15 minutes
        if ($user->id === $this->user_id) {
            return $this->created_at->addMinutes(15) > now();
        }

        // Admins can always edit
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Post owners can edit comments on their posts
        if ($user->id === $this->post->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Check if a user can delete this comment.
     */
    public function canDeleteBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Comment owner can delete
        if ($user->id === $this->user_id) {
            return true;
        }

        // Admins can delete
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        // Post owners can delete comments on their posts
        if ($user->id === $this->post->user_id) {
            return true;
        }

        return false;
    }

    /**
     * Build a threaded comment tree.
     */
    public static function buildTree(Collection $comments): Collection
    {
        $tree = collect();
        $grouped = $comments->groupBy('parent_id');

        // Start with root comments (parent_id is null)
        $rootComments = $grouped->get(null, collect());

        foreach ($rootComments as $comment) {
            $comment->setRelation('replies', static::buildTreeRecursive($comment, $grouped));
            $tree->push($comment);
        }

        return $tree;
    }

    /**
     * Recursively build comment tree.
     */
    protected static function buildTreeRecursive(Comment $comment, Collection $grouped): Collection
    {
        $replies = $grouped->get($comment->id, collect());
        
        foreach ($replies as $reply) {
            $reply->setRelation('replies', static::buildTreeRecursive($reply, $grouped));
        }

        return $replies;
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
     * Scope for root comments only.
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->where('depth', 0)
                    ->orWhereNull('parent_id');
    }

    /**
     * Scope for replies only.
     */
    public function scopeReplies(Builder $query): Builder
    {
        return $query->where('depth', '>', 0)
                    ->whereNotNull('parent_id');
    }

    /**
     * Scope for comments visible to user.
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Scope for comments by depth level.
     */
    public function scopeByDepth(Builder $query, int $depth): Builder
    {
        return $query->where('depth', $depth);
    }

    /**
     * Scope for popular comments.
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->orderBy('likes_count', 'desc')
                    ->orderBy('created_at', 'desc');
    }

    /**
     * Scope for threaded ordering (by path).
     */
    public function scopeThreaded(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(path, CAST(id AS CHAR))')
                    ->orderBy('created_at', 'asc');
    }

    /**
     * Get is_root accessor.
     */
    public function getIsRootAttribute(): bool
    {
        return $this->isRoot();
    }

    /**
     * Get has_replies accessor.
     */
    public function getHasRepliesAttribute(): bool
    {
        return $this->hasReplies();
    }

    /**
     * Get can_reply accessor.
     */
    public function getCanReplyAttribute(): bool
    {
        return $this->canReply();
    }
}
