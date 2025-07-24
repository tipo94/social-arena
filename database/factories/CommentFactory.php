<?php

namespace Database\Factories;

use App\Models\Comment;
use App\Models\User;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Comment>
 */
class CommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Comment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'post_id' => Post::factory(),
            'parent_id' => null,
            'content' => $this->faker->paragraph(rand(1, 3)),
            'type' => $this->faker->randomElement(['text', 'image', 'gif']),
            'likes_count' => 0,
            'replies_count' => 0,
            'depth' => 0,
            'path' => null, // Will be set after creation
            'is_reported' => false,
            'is_hidden' => false,
            'moderated_at' => null,
            'moderated_by' => null,
            'created_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Indicate that the comment is a text comment.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'text',
            'content' => $this->faker->paragraph(rand(1, 2)),
        ]);
    }

    /**
     * Indicate that the comment contains an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'content' => $this->faker->sentence() . ' [Image attached]',
        ]);
    }

    /**
     * Indicate that the comment is a gif.
     */
    public function gif(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'gif',
            'content' => $this->faker->randomElement([
                'That\'s hilarious! 😂',
                'Perfect reaction gif!',
                'This made my day!',
                'So funny!',
            ]),
        ]);
    }

    /**
     * Indicate that the comment is a reply to another comment.
     */
    public function reply(Comment $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => $parent->post_id,
            'parent_id' => $parent->id,
            'depth' => $parent->depth + 1,
            'content' => $this->faker->randomElement([
                'Great point!',
                'I totally agree with you.',
                'Thanks for sharing this!',
                'That\'s an interesting perspective.',
                'Same here!',
                'Well said!',
                'I hadn\'t thought of it that way.',
                'You\'re absolutely right.',
            ]),
        ]);
    }

    /**
     * Indicate that the comment is for a specific post.
     */
    public function forPost(Post $post): static
    {
        return $this->state(fn (array $attributes) => [
            'post_id' => $post->id,
        ]);
    }

    /**
     * Indicate that the comment is from a specific user.
     */
    public function fromUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the comment is popular (has many likes).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'likes_count' => $this->faker->numberBetween(10, 100),
        ]);
    }

    /**
     * Indicate that the comment has replies.
     */
    public function withReplies(int $count = null): static
    {
        $replyCount = $count ?? $this->faker->numberBetween(1, 5);
        
        return $this->state(fn (array $attributes) => [
            'replies_count' => $replyCount,
        ]);
    }

    /**
     * Indicate that the comment is at a specific depth.
     */
    public function atDepth(int $depth): static
    {
        return $this->state(fn (array $attributes) => [
            'depth' => $depth,
        ]);
    }

    /**
     * Indicate that the comment is hidden.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_hidden' => true,
        ]);
    }

    /**
     * Indicate that the comment is reported.
     */
    public function reported(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reported' => true,
        ]);
    }

    /**
     * Indicate that the comment is moderated.
     */
    public function moderated(User $moderator = null): static
    {
        return $this->state(fn (array $attributes) => [
            'moderated_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'moderated_by' => $moderator?->id ?? User::factory(),
        ]);
    }

    /**
     * Indicate that the comment was created recently.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Create a comment with a full reply chain.
     */
    public function withReplyChain(int $depth = 3): static
    {
        return $this->afterCreating(function (Comment $comment) use ($depth) {
            $current = $comment;
            
            for ($i = 1; $i <= $depth; $i++) {
                if ($current->depth >= Comment::MAX_DEPTH) {
                    break;
                }
                
                $reply = Comment::factory()
                    ->reply($current)
                    ->create();
                
                $current->increment('replies_count');
                $current = $reply;
            }
        });
    }

    /**
     * Create a comment with multiple replies at the same level.
     */
    public function withMultipleReplies(int $count = 3): static
    {
        return $this->afterCreating(function (Comment $comment) use ($count) {
            Comment::factory()
                ->count($count)
                ->reply($comment)
                ->create();
            
            $comment->update(['replies_count' => $count]);
        });
    }

    /**
     * Configure the comment after making.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Comment $comment) {
            // Set path for root comments
            if (!$comment->parent_id) {
                $comment->path = null; // Will be set after creation
            }
        })->afterCreating(function (Comment $comment) {
            // Update path for root comments
            if (!$comment->parent_id) {
                $comment->update(['path' => (string) $comment->id]);
            }
        });
    }
} 