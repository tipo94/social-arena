<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Notification extends Model
{
    use HasFactory, SoftDeletes;

    // Notification types constants
    public const TYPE_LIKE = 'like';
    public const TYPE_COMMENT = 'comment';
    public const TYPE_FOLLOW = 'follow';
    public const TYPE_FRIEND_REQUEST = 'friend_request';
    public const TYPE_FRIEND_ACCEPTED = 'friend_accepted';
    public const TYPE_SHARE = 'share';
    public const TYPE_MENTION = 'mention';
    public const TYPE_GROUP_INVITE = 'group_invite';
    public const TYPE_MESSAGE = 'message';
    public const TYPE_POST_EDITED = 'post_edited';
    public const TYPE_SYSTEM = 'system';

    // Priority levels
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'actor_id',
        'type',
        'title',
        'message',
        'action_url',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
        'is_dismissed',
        'is_sent_email',
        'is_sent_push',
        'priority',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'is_dismissed' => 'boolean',
        'is_sent_email' => 'boolean',
        'is_sent_push' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_read',
        'is_unread',
        'time_ago',
        'can_be_dismissed',
    ];

    /**
     * Boot method for model events.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Notification $notification) {
            // Auto-generate action URLs if not provided
            if (!$notification->action_url) {
                $notification->action_url = $notification->generateActionUrl();
            }
        });
    }

    /**
     * Get the user that owns the notification.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user that triggered the notification.
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the notifiable model.
     */
    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    // Accessors

    /**
     * Check if notification is read.
     */
    public function getIsReadAttribute(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if notification is unread.
     */
    public function getIsUnreadAttribute(): bool
    {
        return is_null($this->read_at);
    }

    /**
     * Get human-readable time ago.
     */
    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Check if notification can be dismissed.
     */
    public function getCanBeDismissedAttribute(): bool
    {
        return !$this->is_dismissed && in_array($this->type, [
            self::TYPE_LIKE,
            self::TYPE_COMMENT,
            self::TYPE_FOLLOW,
            self::TYPE_SHARE,
        ]);
    }

    // Helper Methods

    /**
     * Mark notification as read.
     */
    public function markAsRead(): bool
    {
        if ($this->is_read) {
            return true;
        }

        return $this->update(['read_at' => now()]);
    }

    /**
     * Mark notification as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update(['read_at' => null]);
    }

    /**
     * Dismiss the notification.
     */
    public function dismiss(): bool
    {
        return $this->update(['is_dismissed' => true]);
    }

    /**
     * Check if notification is for a specific user.
     */
    public function isFor(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Check if notification was triggered by a specific user.
     */
    public function isTriggeredBy(User $user): bool
    {
        return $this->actor_id === $user->id;
    }

    /**
     * Generate action URL based on notification type.
     */
    protected function generateActionUrl(): ?string
    {
        return match($this->type) {
            self::TYPE_LIKE, self::TYPE_COMMENT, self::TYPE_SHARE => 
                $this->notifiable_type === 'App\\Models\\Post' ? "/posts/{$this->notifiable_id}" : null,
            self::TYPE_FOLLOW => 
                $this->actor_id ? "/profile/{$this->actor_id}" : null,
            self::TYPE_FRIEND_REQUEST, self::TYPE_FRIEND_ACCEPTED => 
                '/friends/requests',
            self::TYPE_MESSAGE => 
                '/messages',
            self::TYPE_GROUP_INVITE => 
                $this->data['group_id'] ?? null ? "/groups/{$this->data['group_id']}" : '/groups',
            default => null,
        };
    }

    // Query Scopes

    /**
     * Scope to only unread notifications.
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    /**
     * Scope to only read notifications.
     */
    public function scopeRead(Builder $query): Builder
    {
        return $query->whereNotNull('read_at');
    }

    /**
     * Scope to exclude dismissed notifications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_dismissed', false);
    }

    /**
     * Scope to only dismissed notifications.
     */
    public function scopeDismissed(Builder $query): Builder
    {
        return $query->where('is_dismissed', true);
    }

    /**
     * Scope by notification type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by priority level.
     */
    public function scopeWithPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope by actor (who triggered the notification).
     */
    public function scopeTriggeredBy(Builder $query, User $actor): Builder
    {
        return $query->where('actor_id', $actor->id);
    }

    /**
     * Scope for recent notifications.
     */
    public function scopeRecent(Builder $query, int $hours = 24): Builder
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }

    /**
     * Scope for high priority notifications.
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    // Static Helper Methods

    /**
     * Get all available notification types.
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_LIKE,
            self::TYPE_COMMENT,
            self::TYPE_FOLLOW,
            self::TYPE_FRIEND_REQUEST,
            self::TYPE_FRIEND_ACCEPTED,
            self::TYPE_SHARE,
            self::TYPE_MENTION,
            self::TYPE_GROUP_INVITE,
            self::TYPE_MESSAGE,
            self::TYPE_POST_EDITED,
            self::TYPE_SYSTEM,
        ];
    }

    /**
     * Get all priority levels.
     */
    public static function getPriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT,
        ];
    }

    /**
     * Get notification count for user.
     */
    public static function getCountForUser(User $user, bool $unreadOnly = false): int
    {
        $query = static::where('user_id', $user->id)->active();
        
        if ($unreadOnly) {
            $query->unread();
        }

        return $query->count();
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllAsReadForUser(User $user): int
    {
        return static::where('user_id', $user->id)
                     ->unread()
                     ->update(['read_at' => now()]);
    }

    /**
     * Delete old read notifications for a user.
     */
    public static function cleanupOldNotifications(User $user, int $daysOld = 30): int
    {
        return static::where('user_id', $user->id)
                     ->read()
                     ->where('read_at', '<', now()->subDays($daysOld))
                     ->delete();
    }
}
