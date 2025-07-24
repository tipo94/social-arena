<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $this->action_url,
            'data' => $this->data,
            'priority' => $this->priority,
            
            // Status flags
            'is_read' => $this->is_read,
            'is_unread' => $this->is_unread,
            'is_dismissed' => $this->is_dismissed,
            'can_be_dismissed' => $this->can_be_dismissed,
            
            // Delivery status
            'is_sent_email' => $this->is_sent_email,
            'is_sent_push' => $this->is_sent_push,
            
            // Actor information (who triggered the notification)
            'actor' => $this->when($this->relationLoaded('actor') && $this->actor, [
                'id' => $this->actor?->id,
                'name' => $this->actor?->name,
                'username' => $this->actor?->username,
                'avatar_url' => $this->actor?->profile?->avatar_url,
            ]),
            
            // Related entity information
            'notifiable' => $this->when($this->relationLoaded('notifiable') && $this->notifiable, function () {
                $notifiable = $this->notifiable;
                
                // Return different data based on notifiable type
                return match(get_class($notifiable)) {
                    'App\Models\Post' => [
                        'type' => 'post',
                        'id' => $notifiable->id,
                        'content' => $this->truncateText($notifiable->content, 100),
                        'user_id' => $notifiable->user_id,
                        'created_at' => $notifiable->created_at,
                    ],
                    'App\Models\Comment' => [
                        'type' => 'comment',
                        'id' => $notifiable->id,
                        'content' => $this->truncateText($notifiable->content, 100),
                        'post_id' => $notifiable->post_id,
                        'user_id' => $notifiable->user_id,
                        'created_at' => $notifiable->created_at,
                    ],
                    'App\Models\Friendship' => [
                        'type' => 'friendship',
                        'id' => $notifiable->id,
                        'status' => $notifiable->status,
                        'user_id' => $notifiable->user_id,
                        'friend_id' => $notifiable->friend_id,
                        'created_at' => $notifiable->created_at,
                    ],
                    default => [
                        'type' => 'unknown',
                        'id' => $notifiable->id,
                    ],
                };
            }),
            
            // Timestamps
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Human-readable time
            'time_ago' => $this->time_ago,
            
            // Additional computed properties
            'age_in_hours' => $this->created_at ? $this->created_at->diffInHours(now()) : null,
            'is_recent' => $this->created_at ? $this->created_at->isAfter(now()->subHours(24)) : false,
        ];
    }

    /**
     * Truncate text for display.
     */
    private function truncateText(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
} 