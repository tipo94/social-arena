<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

class Share extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'shareable_id',
        'shareable_type',
        'share_type',
        'platform',
        'content',
        'metadata',
        'shared_to_user_id',
        'shared_to_group_id',
        'visibility',
        'is_quote_share',
        'is_private_share',
        'shared_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'is_quote_share' => 'boolean',
        'is_private_share' => 'boolean',
        'shared_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_repost',
        'is_external_share',
    ];

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Share $share) {
            $share->shared_at = $share->shared_at ?? now();
        });

        static::created(function (Share $share) {
            // Update share count on the shareable content
            if ($share->shareable) {
                $share->shareable->incrementShares();
            }
        });

        static::deleted(function (Share $share) {
            // Decrement share count on the shareable content
            if ($share->shareable && method_exists($share->shareable, 'decrementShares')) {
                $share->shareable->decrementShares();
            }
        });
    }

    /**
     * Get the user who shared the content.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the shareable content (polymorphic).
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user this was shared to (for private shares).
     */
    public function sharedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_to_user_id');
    }

    /**
     * Get the group this was shared to.
     */
    public function sharedToGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'shared_to_group_id');
    }

    /**
     * Check if this is a repost (internal share).
     */
    public function getIsRepostAttribute(): bool
    {
        return in_array($this->share_type, ['repost', 'quote_repost', 'internal']);
    }

    /**
     * Check if this is an external share.
     */
    public function getIsExternalShareAttribute(): bool
    {
        return in_array($this->share_type, ['external', 'link_share']) || !empty($this->platform);
    }

    /**
     * Get share URL for external platforms.
     */
    public function getShareUrl(?string $platform = null): ?string
    {
        if (!$this->is_external_share) {
            return null;
        }

        $platform = $platform ?? $this->platform;
        $baseUrl = config('app.url');
        $contentUrl = '';

        // Generate content URL based on shareable type
        switch ($this->shareable_type) {
            case Post::class:
                $contentUrl = "{$baseUrl}/posts/{$this->shareable_id}";
                break;
            default:
                return null;
        }

        $encodedUrl = urlencode($contentUrl);
        $title = urlencode($this->getShareTitle());

        return match ($platform) {
            'twitter' => "https://twitter.com/intent/tweet?url={$encodedUrl}&text={$title}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}",
            'reddit' => "https://reddit.com/submit?url={$encodedUrl}&title={$title}",
            'whatsapp' => "https://wa.me/?text={$title}%20{$encodedUrl}",
            'telegram' => "https://t.me/share/url?url={$encodedUrl}&text={$title}",
            default => $contentUrl,
        };
    }

    /**
     * Get share title for external platforms.
     */
    public function getShareTitle(): string
    {
        if ($this->content) {
            return substr($this->content, 0, 100) . (strlen($this->content) > 100 ? '...' : '');
        }

        if ($this->shareable instanceof Post) {
            $content = strip_tags($this->shareable->content ?? '');
            return substr($content, 0, 100) . (strlen($content) > 100 ? '...' : '');
        }

        return 'Check out this content!';
    }

    /**
     * Check if user can share this content.
     */
    public function canBeSharedBy(?User $user = null): bool
    {
        if (!$user) {
            return false;
        }

        // Can't share your own content to yourself (for reposts)
        if ($this->is_repost && $this->shareable->user_id === $user->id) {
            return false;
        }

        // Check if content allows resharing
        if ($this->shareable && method_exists($this->shareable, 'allowsInteraction')) {
            return $this->shareable->allowsInteraction('reshare');
        }

        return true;
    }

    /**
     * Check if user can view this share.
     */
    public function isVisibleTo(?User $user = null): bool
    {
        if ($this->is_private_share) {
            return $user && ($user->id === $this->user_id || $user->id === $this->shared_to_user_id);
        }

        // Check shareable content visibility
        if ($this->shareable && method_exists($this->shareable, 'isVisibleTo')) {
            return $this->shareable->isVisibleTo($user);
        }

        return $this->visibility === 'public';
    }

    /**
     * Scope for shares of a specific type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('share_type', $type);
    }

    /**
     * Scope for reposts (internal shares).
     */
    public function scopeReposts(Builder $query): Builder
    {
        return $query->whereIn('share_type', ['repost', 'quote_repost', 'internal']);
    }

    /**
     * Scope for external shares.
     */
    public function scopeExternal(Builder $query): Builder
    {
        return $query->whereIn('share_type', ['external', 'link_share'])
                    ->orWhereNotNull('platform');
    }

    /**
     * Scope for quote shares.
     */
    public function scopeQuoteShares(Builder $query): Builder
    {
        return $query->where('is_quote_share', true);
    }

    /**
     * Scope for shares to a specific platform.
     */
    public function scopeToPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Scope for shares by a specific user.
     */
    public function scopeByUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for shares visible to a user.
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        if (!$user) {
            return $query->where('visibility', 'public')
                        ->where('is_private_share', false);
        }

        return $query->where(function ($q) use ($user) {
            $q->where('visibility', 'public')
              ->orWhere('user_id', $user->id)
              ->orWhere('shared_to_user_id', $user->id)
                             ->orWhere(function ($subQ) use ($user) {
                   $subQ->where('visibility', 'friends');
                   // TODO: Add friend relationship check when Friendship model is implemented
                   // ->whereHas('user.friends', function ($friendsQ) use ($user) {
                   //     $friendsQ->where('friend_id', $user->id);
                   // });
               });
        });
    }

    /**
     * Scope for recent shares.
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('shared_at', '>=', now()->subDays($days));
    }

    /**
     * Get popular shares by engagement.
     */
    public function scopePopular(Builder $query): Builder
    {
        return $query->withCount(['shareable.likes', 'shareable.comments'])
                    ->orderByRaw('(shareable_likes_count + shareable_comments_count) DESC');
    }

    /**
     * Get trending shares.
     */
    public function scopeTrending(Builder $query, int $hours = 24): Builder
    {
        return $query->where('shared_at', '>=', now()->subHours($hours))
                    ->withCount(['shareable.likes', 'shareable.comments'])
                    ->orderByRaw('(shareable_likes_count + shareable_comments_count) / GREATEST(TIMESTAMPDIFF(HOUR, shared_at, NOW()), 1) DESC');
    }
} 