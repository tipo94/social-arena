<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Notification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = Notification::getTypes();
        $type = $this->faker->randomElement($types);
        
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory(),
            'type' => $type,
            'title' => $this->getTitleForType($type),
            'message' => $this->getMessageForType($type),
            'action_url' => $this->getActionUrlForType($type),
            'notifiable_type' => null,
            'notifiable_id' => null,
            'data' => $this->getDataForType($type),
            'read_at' => $this->faker->optional(0.6)->dateTimeBetween('-1 week', 'now'),
            'is_dismissed' => $this->faker->boolean(20), // 20% chance of being dismissed
            'is_sent_email' => $this->faker->boolean(30), // 30% chance of email sent
            'is_sent_push' => $this->faker->boolean(80), // 80% chance of push sent
            'priority' => $this->faker->randomElement(Notification::getPriorities()),
            'created_at' => $this->faker->dateTimeBetween('-2 weeks', 'now'),
        ];
    }

    /**
     * Create a like notification.
     */
    public function like(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_LIKE,
                'title' => 'Your post was liked',
                'message' => fake()->name() . ' liked your post',
                'notifiable_type' => 'App\\Models\\Post',
                'notifiable_id' => Post::factory(),
                'action_url' => '/posts/' . fake()->numberBetween(1, 100),
                'data' => [
                    'post_title' => fake()->sentence(8),
                    'liker_name' => fake()->name(),
                ],
                'priority' => Notification::PRIORITY_NORMAL,
            ];
        });
    }

    /**
     * Create a comment notification.
     */
    public function comment(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_COMMENT,
                'title' => 'New comment on your post',
                'message' => fake()->name() . ' commented on your post',
                'notifiable_type' => 'App\\Models\\Comment',
                'notifiable_id' => Comment::factory(),
                'action_url' => '/posts/' . fake()->numberBetween(1, 100),
                'data' => [
                    'post_title' => fake()->sentence(8),
                    'comment_content' => fake()->sentence(12),
                    'commenter_name' => fake()->name(),
                    'post_id' => fake()->numberBetween(1, 100),
                ],
                'priority' => Notification::PRIORITY_NORMAL,
            ];
        });
    }

    /**
     * Create a follow notification.
     */
    public function follow(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_FOLLOW,
                'title' => 'New follower',
                'message' => fake()->name() . ' started following you',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'action_url' => '/profile/' . fake()->numberBetween(1, 100),
                'data' => [
                    'follower_name' => fake()->name(),
                    'follower_id' => fake()->numberBetween(1, 100),
                ],
                'priority' => Notification::PRIORITY_NORMAL,
            ];
        });
    }

    /**
     * Create a friend request notification.
     */
    public function friendRequest(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_FRIEND_REQUEST,
                'title' => 'New friend request',
                'message' => fake()->name() . ' sent you a friend request',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'action_url' => '/friends/requests',
                'data' => [
                    'requester_name' => fake()->name(),
                    'requester_id' => fake()->numberBetween(1, 100),
                ],
                'priority' => Notification::PRIORITY_HIGH,
            ];
        });
    }

    /**
     * Create a friend accepted notification.
     */
    public function friendAccepted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_FRIEND_ACCEPTED,
                'title' => 'Friend request accepted',
                'message' => fake()->name() . ' accepted your friend request',
                'notifiable_type' => null,
                'notifiable_id' => null,
                'action_url' => '/profile/' . fake()->numberBetween(1, 100),
                'data' => [
                    'accepter_name' => fake()->name(),
                    'accepter_id' => fake()->numberBetween(1, 100),
                ],
                'priority' => Notification::PRIORITY_HIGH,
            ];
        });
    }

    /**
     * Create a share notification.
     */
    public function share(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => Notification::TYPE_SHARE,
                'title' => 'Your post was shared',
                'message' => fake()->name() . ' shared your post',
                'notifiable_type' => 'App\\Models\\Post',
                'notifiable_id' => Post::factory(),
                'action_url' => '/posts/' . fake()->numberBetween(1, 100),
                'data' => [
                    'post_title' => fake()->sentence(8),
                    'sharer_name' => fake()->name(),
                ],
                'priority' => Notification::PRIORITY_NORMAL,
            ];
        });
    }

    /**
     * Create an unread notification.
     */
    public function unread(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => null,
            ];
        });
    }

    /**
     * Create a read notification.
     */
    public function read(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * Create a dismissed notification.
     */
    public function dismissed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_dismissed' => true,
                'read_at' => fake()->dateTimeBetween('-1 week', 'now'),
            ];
        });
    }

    /**
     * Create a high priority notification.
     */
    public function highPriority(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => Notification::PRIORITY_HIGH,
            ];
        });
    }

    /**
     * Create an urgent notification.
     */
    public function urgent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'priority' => Notification::PRIORITY_URGENT,
            ];
        });
    }

    /**
     * Create a recent notification.
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => fake()->dateTimeBetween('-24 hours', 'now'),
            ];
        });
    }

    /**
     * Create an old notification.
     */
    public function old(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
                'read_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
            ];
        });
    }

    /**
     * Get title for notification type.
     */
    protected function getTitleForType(string $type): string
    {
        return match($type) {
            Notification::TYPE_LIKE => 'Your post was liked',
            Notification::TYPE_COMMENT => 'New comment on your post',
            Notification::TYPE_FOLLOW => 'New follower',
            Notification::TYPE_FRIEND_REQUEST => 'New friend request',
            Notification::TYPE_FRIEND_ACCEPTED => 'Friend request accepted',
            Notification::TYPE_SHARE => 'Your post was shared',
            Notification::TYPE_MENTION => 'You were mentioned',
            Notification::TYPE_GROUP_INVITE => 'Group invitation',
            Notification::TYPE_MESSAGE => 'New message',
            Notification::TYPE_POST_EDITED => 'Post edited',
            Notification::TYPE_SYSTEM => 'System notification',
            default => 'Notification',
        };
    }

    /**
     * Get message for notification type.
     */
    protected function getMessageForType(string $type): string
    {
        $name = fake()->name();
        
        return match($type) {
            Notification::TYPE_LIKE => "{$name} liked your post",
            Notification::TYPE_COMMENT => "{$name} commented on your post",
            Notification::TYPE_FOLLOW => "{$name} started following you",
            Notification::TYPE_FRIEND_REQUEST => "{$name} sent you a friend request",
            Notification::TYPE_FRIEND_ACCEPTED => "{$name} accepted your friend request",
            Notification::TYPE_SHARE => "{$name} shared your post",
            Notification::TYPE_MENTION => "{$name} mentioned you in a post",
            Notification::TYPE_GROUP_INVITE => "You've been invited to join a group",
            Notification::TYPE_MESSAGE => "You have a new message",
            Notification::TYPE_POST_EDITED => "A post you're following was edited",
            Notification::TYPE_SYSTEM => "System notification message",
            default => "You have a new notification",
        };
    }

    /**
     * Get action URL for notification type.
     */
    protected function getActionUrlForType(string $type): ?string
    {
        return match($type) {
            Notification::TYPE_LIKE, Notification::TYPE_COMMENT, Notification::TYPE_SHARE => 
                '/posts/' . fake()->numberBetween(1, 100),
            Notification::TYPE_FOLLOW => 
                '/profile/' . fake()->numberBetween(1, 100),
            Notification::TYPE_FRIEND_REQUEST, Notification::TYPE_FRIEND_ACCEPTED => 
                '/friends/requests',
            Notification::TYPE_MESSAGE => 
                '/messages',
            Notification::TYPE_GROUP_INVITE => 
                '/groups',
            default => null,
        };
    }

    /**
     * Get data for notification type.
     */
    protected function getDataForType(string $type): array
    {
        return match($type) {
            Notification::TYPE_LIKE => [
                'post_title' => fake()->sentence(8),
                'liker_name' => fake()->name(),
            ],
            Notification::TYPE_COMMENT => [
                'post_title' => fake()->sentence(8),
                'comment_content' => fake()->sentence(12),
                'commenter_name' => fake()->name(),
                'post_id' => fake()->numberBetween(1, 100),
            ],
            Notification::TYPE_FOLLOW => [
                'follower_name' => fake()->name(),
                'follower_id' => fake()->numberBetween(1, 100),
            ],
            Notification::TYPE_FRIEND_REQUEST => [
                'requester_name' => fake()->name(),
                'requester_id' => fake()->numberBetween(1, 100),
            ],
            Notification::TYPE_FRIEND_ACCEPTED => [
                'accepter_name' => fake()->name(),
                'accepter_id' => fake()->numberBetween(1, 100),
            ],
            Notification::TYPE_SHARE => [
                'post_title' => fake()->sentence(8),
                'sharer_name' => fake()->name(),
            ],
            default => [],
        };
    }
} 