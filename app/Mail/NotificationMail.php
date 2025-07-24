<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public User $user,
        public string $type,
        public array $data
    ) {
        $this->queue = config('mail.notifications.queue');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->getSubjectForType($this->type);
        
        return new Envelope(
            subject: $subject,
            from: config('mail.from.address'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.notification',
            with: [
                'user' => $this->user,
                'type' => $this->type,
                'data' => $this->data,
                'notificationData' => $this->prepareNotificationData(),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get subject line for notification type
     */
    protected function getSubjectForType(string $type): string
    {
        return match($type) {
            'friend_request' => 'New Friend Request',
            'comment' => 'New Comment on Your Post',
            'like' => 'Someone Liked Your Post',
            'mention' => 'You Were Mentioned in a Post',
            'group_invitation' => 'Group Invitation',
            'message' => 'New Message',
            'post_shared' => 'Your Post Was Shared',
            default => 'New Notification',
        };
    }

    /**
     * Prepare notification data for the template
     */
    protected function prepareNotificationData(): array
    {
        return match($this->type) {
            'friend_request' => [
                'title' => 'New Friend Request',
                'message' => "{$this->data['sender_name']} sent you a friend request.",
                'action_text' => 'View Request',
                'action_url' => config('app.url') . '/friends/requests',
            ],
            'comment' => [
                'title' => 'New Comment',
                'message' => "{$this->data['commenter_name']} commented on your post: \"{$this->data['post_title']}\"",
                'action_text' => 'View Comment',
                'action_url' => config('app.url') . "/posts/{$this->data['post_id']}",
            ],
            'like' => [
                'title' => 'Post Liked',
                'message' => "{$this->data['liker_name']} liked your post: \"{$this->data['post_title']}\"",
                'action_text' => 'View Post',
                'action_url' => config('app.url') . "/posts/{$this->data['post_id']}",
            ],
            'mention' => [
                'title' => 'You Were Mentioned',
                'message' => "{$this->data['mentioner_name']} mentioned you in a post.",
                'action_text' => 'View Post',
                'action_url' => config('app.url') . "/posts/{$this->data['post_id']}",
            ],
            'group_invitation' => [
                'title' => 'Group Invitation',
                'message' => "You've been invited to join the group \"{$this->data['group_name']}\"",
                'action_text' => 'View Invitation',
                'action_url' => config('app.url') . "/groups/{$this->data['group_id']}/invitations",
            ],
            'message' => [
                'title' => 'New Message',
                'message' => "{$this->data['sender_name']} sent you a message.",
                'action_text' => 'View Message',
                'action_url' => config('app.url') . '/messages',
            ],
            default => [
                'title' => 'New Notification',
                'message' => 'You have a new notification.',
                'action_text' => 'View Notification',
                'action_url' => config('app.url') . '/notifications',
            ],
        };
    }
} 