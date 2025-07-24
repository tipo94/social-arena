<?php

namespace App\Services;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Friendship;
use App\Models\Notification;
use App\Mail\NotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use App\Events\NotificationCreated;
use App\Events\NotificationRead;
use App\Events\NotificationBulkRead;

class NotificationService
{
    /**
     * Main notification creation method
     */
    public function createNotification(
        User $user,
        string $type,
        string $title,
        string $message,
        ?User $actor = null,
        ?Model $notifiable = null,
        array $data = [],
        string $priority = Notification::PRIORITY_NORMAL,
        ?string $actionUrl = null
    ): Notification {
        // Check user preferences
        if (!$this->userWantsNotification($user, $type)) {
            Log::info('Notification skipped - user preferences', [
                'user_id' => $user->id,
                'type' => $type,
            ]);
            return new Notification();
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'actor_id' => $actor?->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'notifiable_type' => $notifiable ? get_class($notifiable) : null,
            'notifiable_id' => $notifiable?->id,
            'data' => $data,
            'priority' => $priority,
        ]);

        // Send email notification if enabled
        if ($this->shouldSendEmail($user, $type)) {
            $this->sendEmailNotification($notification);
        }

        // Broadcast real-time notification
        broadcast(new NotificationCreated($notification))->toOthers();

        // Clear user notification cache
        $this->clearUserNotificationCache($user);

        Log::info('Notification created', [
            'id' => $notification->id,
            'user_id' => $user->id,
            'type' => $type,
            'actor_id' => $actor?->id,
        ]);

        return $notification;
    }

    /**
     * Create like notification
     */
    public function createLikeNotification(User $actor, Post $post): ?Notification
    {
        if ($actor->id === $post->user_id) {
            return null; // Don't notify users about their own likes
        }

        return $this->createNotification(
            user: $post->user,
            type: Notification::TYPE_LIKE,
            title: 'New Like',
            message: "{$actor->name} liked your post",
            actor: $actor,
            notifiable: $post,
            data: [
                'post_content_preview' => $this->truncateText($post->content, 50),
            ],
            priority: Notification::PRIORITY_NORMAL
        );
    }

    /**
     * Create comment notification
     */
    public function createCommentNotification(User $actor, Comment $comment): ?Notification
    {
        $post = $comment->post;
        
        if ($actor->id === $post->user_id) {
            return null; // Don't notify users about comments on their own posts by themselves
        }

        return $this->createNotification(
            user: $post->user,
            type: Notification::TYPE_COMMENT,
            title: 'New Comment',
            message: "{$actor->name} commented on your post",
            actor: $actor,
            notifiable: $comment,
            data: [
                'post_id' => $post->id,
                'comment_content_preview' => $this->truncateText($comment->content, 100),
                'post_content_preview' => $this->truncateText($post->content, 50),
            ],
            priority: Notification::PRIORITY_NORMAL
        );
    }

    /**
     * Create follow notification
     */
    public function createFollowNotification(User $follower, User $following): ?Notification
    {
        return $this->createNotification(
            user: $following,
            type: Notification::TYPE_FOLLOW,
            title: 'New Follower',
            message: "{$follower->name} started following you",
            actor: $follower,
            notifiable: null,
            data: [
                'follower_username' => $follower->username,
            ],
            priority: Notification::PRIORITY_NORMAL
        );
    }

    /**
     * Create friend request notification
     */
    public function createFriendRequestNotification(User $sender, User $recipient): ?Notification
    {
        return $this->createNotification(
            user: $recipient,
            type: Notification::TYPE_FRIEND_REQUEST,
            title: 'Friend Request',
            message: "{$sender->name} sent you a friend request",
            actor: $sender,
            notifiable: null,
            data: [
                'sender_username' => $sender->username,
            ],
            priority: Notification::PRIORITY_HIGH
        );
    }

    /**
     * Create friend accepted notification
     */
    public function createFriendAcceptedNotification(User $accepter, User $requester): ?Notification
    {
        return $this->createNotification(
            user: $requester,
            type: Notification::TYPE_FRIEND_ACCEPTED,
            title: 'Friend Request Accepted',
            message: "{$accepter->name} accepted your friend request",
            actor: $accepter,
            notifiable: null,
            data: [
                'accepter_username' => $accepter->username,
            ],
            priority: Notification::PRIORITY_HIGH
        );
    }

    /**
     * Create share notification
     */
    public function createShareNotification(User $sharer, Post $originalPost): ?Notification
    {
        if ($sharer->id === $originalPost->user_id) {
            return null; // Don't notify users about their own shares
        }

        return $this->createNotification(
            user: $originalPost->user,
            type: Notification::TYPE_SHARE,
            title: 'Post Shared',
            message: "{$sharer->name} shared your post",
            actor: $sharer,
            notifiable: $originalPost,
            data: [
                'post_content_preview' => $this->truncateText($originalPost->content, 50),
            ],
            priority: Notification::PRIORITY_NORMAL
        );
    }

    /**
     * Get user notifications with pagination and caching
     */
    public function getUserNotifications(User $user, array $options = [])
    {
        $cacheKey = "user_notifications_{$user->id}_" . md5(serialize($options));
        $cacheTtl = 300; // 5 minutes

        return Cache::remember($cacheKey, $cacheTtl, function () use ($user, $options) {
            $query = $user->notifications()
                ->with(['actor', 'notifiable'])
                ->orderBy('created_at', 'desc');

            // Apply filters
            if (!empty($options['type'])) {
                $query->where('type', $options['type']);
            }

            if (!empty($options['unread_only'])) {
                $query->whereNull('read_at');
            }

            if (!empty($options['priority'])) {
                $query->where('priority', $options['priority']);
            }

            // Pagination
            $perPage = $options['per_page'] ?? 20;
            $page = $options['page'] ?? 1;

            return $query->paginate($perPage, ['*'], 'page', $page);
        });
    }

    /**
     * Get notification statistics for a user
     */
    public function getNotificationStats(User $user): array
    {
        $cacheKey = "user_notification_stats_{$user->id}";
        $cacheTtl = 300; // 5 minutes

        return Cache::remember($cacheKey, $cacheTtl, function () use ($user) {
            $notifications = $user->notifications();

            return [
                'total' => $notifications->count(),
                'unread' => $notifications->whereNull('read_at')->count(),
                'today' => $notifications->where('created_at', '>=', now()->startOfDay())->count(),
                'this_week' => $notifications->where('created_at', '>=', now()->startOfWeek())->count(),
                'by_type' => $notifications->selectRaw('type, count(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type')
                    ->toArray(),
                'high_priority' => $notifications->where('priority', Notification::PRIORITY_HIGH)->count(),
            ];
        });
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Notification $notification): Notification
    {
        if (!$notification->read_at) {
            $notification->update(['read_at' => now()]);
            
            // Broadcast the read status change
            broadcast(new NotificationRead($notification))->toOthers();
            
            // Clear user notification cache
            $this->clearUserNotificationCache($notification->user);
        }

        return $notification;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(User $user): int
    {
        $count = $user->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        if ($count > 0) {
            // Broadcast bulk read status change
            broadcast(new NotificationBulkRead($user, $count))->toOthers();
            
            // Clear user notification cache
            $this->clearUserNotificationCache($user);
        }

        return $count;
    }

    /**
     * Dismiss a notification
     */
    public function dismissNotification(Notification $notification): Notification
    {
        $notification->update(['is_dismissed' => true]);
        $this->clearUserNotificationCache($notification->user);
        return $notification;
    }

    /**
     * Check if user wants to receive notifications of this type
     */
    private function userWantsNotification(User $user, string $type): bool
    {
        // Basic check - can be enhanced with user preferences
        $userPreferences = $user->notification_preferences ?? [];
        
        return $userPreferences[$type] ?? true;
    }

    /**
     * Check if we should send email notification
     */
    private function shouldSendEmail(User $user, string $type): bool
    {
        if (!$user->email_verified_at) {
            return false;
        }

        $emailPreferences = $user->email_preferences ?? [];
        return $emailPreferences[$type] ?? false;
    }

    /**
     * Send email notification
     */
    private function sendEmailNotification(Notification $notification): void
    {
        try {
            Mail::to($notification->user->email)
                ->queue(new NotificationMail(
                    $notification->user,
                    $notification->type,
                    array_merge($notification->data ?? [], [
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'action_url' => $notification->action_url,
                        'actor_name' => $notification->actor?->name,
                    ])
                ));
        } catch (\Exception $e) {
            Log::error('Failed to send notification email', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear user notification cache
     */
    private function clearUserNotificationCache(User $user): void
    {
        Cache::forget("user_notifications_{$user->id}_*");
        Cache::forget("user_notification_stats_{$user->id}");
    }

    /**
     * Truncate text for previews
     */
    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }

    /**
     * Cleanup old notifications
     */
    public function cleanupOldNotifications(int $daysOld = 30): int
    {
        return Notification::where('created_at', '<', now()->subDays($daysOld))
            ->where('is_dismissed', true)
            ->whereNotNull('read_at')
            ->delete();
    }
} 