<?php

namespace App\Events;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Notification $notification;
    public User $user;

    /**
     * Create a new event instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification->load(['actor', 'notifiable']);
        $this->user = $notification->user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->user->id}.notifications"),
            new PrivateChannel("notifications.{$this->notification->type}"),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => [
                'id' => $this->notification->id,
                'type' => $this->notification->type,
                'title' => $this->notification->title,
                'message' => $this->notification->message,
                'action_url' => $this->notification->action_url,
                'data' => $this->notification->data,
                'priority' => $this->notification->priority,
                'is_read' => $this->notification->is_read,
                'is_unread' => $this->notification->is_unread,
                'is_dismissed' => $this->notification->is_dismissed,
                'can_be_dismissed' => $this->notification->can_be_dismissed,
                'actor' => $this->notification->actor ? [
                    'id' => $this->notification->actor->id,
                    'name' => $this->notification->actor->name,
                    'username' => $this->notification->actor->username,
                    'avatar_url' => $this->notification->actor->profile?->avatar_url,
                ] : null,
                'created_at' => $this->notification->created_at,
                'time_ago' => $this->notification->time_ago,
                'is_recent' => $this->notification->is_recent,
            ],
            'user_id' => $this->user->id,
            'timestamp' => now()->toISOString(),
        ];
    }
} 