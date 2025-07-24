<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'group_id' => null,
            'content' => $this->faker->paragraphs(rand(1, 3), true),
            'type' => $this->faker->randomElement(['text', 'image', 'video', 'link', 'book_review']),
            'metadata' => $this->faker->randomElement([null, ['book_title' => $this->faker->sentence(3)]]),
            'visibility' => $this->faker->randomElement(['public', 'friends', 'private']),
            'custom_audience' => null,
            'allow_resharing' => $this->faker->boolean(80),
            'allow_comments' => $this->faker->boolean(90),
            'allow_reactions' => $this->faker->boolean(95),
            'visibility_expires_at' => null,
            'visibility_history' => null,
            'visibility_changed_at' => null,
            'likes_count' => 0,
            'comments_count' => 0,
            'shares_count' => 0,
            'views_count' => $this->faker->numberBetween(0, 100),
            'reach_count' => $this->faker->numberBetween(0, 50),
            'is_reported' => false,
            'is_hidden' => false,
            'moderated_at' => null,
            'moderated_by' => null,
            'published_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_scheduled' => false,
            'edit_history' => null,
            'last_edited_at' => null,
            'last_edited_by' => null,
            'edit_count' => 0,
            'is_edited' => false,
            'allow_editing' => true,
            'editing_locked_at' => null,
            'edit_deadline' => null,
            'deletion_reason' => null,
            'deleted_by' => null,
            'deletion_scheduled_at' => null,
            'permanent_deletion_at' => null,
            'can_be_restored' => true,
            'current_version' => 1,
            'original_content' => null,
            'edit_notifications_sent' => false,
            'notification_recipients' => null,
        ];
    }

    /**
     * Indicate that the post is of type "text".
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'metadata' => null,
        ]);
    }

    /**
     * Indicate that the post is of type "image".
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'metadata' => [
                'image_count' => $this->faker->numberBetween(1, 5),
                'has_alt_text' => $this->faker->boolean(50),
            ],
        ]);
    }

    /**
     * Indicate that the post is of type "video".
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'metadata' => [
                'duration' => $this->faker->numberBetween(10, 300),
                'resolution' => $this->faker->randomElement(['720p', '1080p', '4K']),
            ],
        ]);
    }

    /**
     * Indicate that the post is of type "book_review".
     */
    public function bookReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'book_review',
            'metadata' => [
                'book_title' => $this->faker->sentence(3),
                'book_author' => $this->faker->name,
                'rating' => $this->faker->numberBetween(1, 5),
                'isbn' => $this->faker->isbn13,
            ],
        ]);
    }

    /**
     * Indicate that the post is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the post is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'private',
        ]);
    }

    /**
     * Indicate that the post is for friends only.
     */
    public function friendsOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'friends',
        ]);
    }

    /**
     * Indicate that the post belongs to a group.
     */
    public function inGroup(Group $group = null): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group?->id ?? Group::factory(),
            'visibility' => 'group',
        ]);
    }

    /**
     * Indicate that the post is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_scheduled' => true,
            'published_at' => $this->faker->dateTimeBetween('now', '+1 week'),
        ]);
    }

    /**
     * Indicate that the post has been edited.
     */
    public function edited(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_edited' => true,
            'edit_count' => $this->faker->numberBetween(1, 5),
            'last_edited_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
        ]);
    }

    /**
     * Indicate that the post has high engagement.
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'likes_count' => $this->faker->numberBetween(50, 500),
            'comments_count' => $this->faker->numberBetween(10, 100),
            'shares_count' => $this->faker->numberBetween(5, 50),
            'views_count' => $this->faker->numberBetween(100, 1000),
            'reach_count' => $this->faker->numberBetween(50, 500),
        ]);
    }

    /**
     * Indicate that the post doesn't allow reactions.
     */
    public function noReactions(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_reactions' => false,
        ]);
    }

    /**
     * Indicate that the post doesn't allow comments.
     */
    public function noComments(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_comments' => false,
        ]);
    }

    /**
     * Indicate that the post doesn't allow sharing.
     */
    public function noSharing(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_resharing' => false,
        ]);
    }

    /**
     * Indicate that the post is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the post was published recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
} 