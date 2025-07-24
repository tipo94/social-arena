<?php

namespace Database\Factories;

use App\Models\Share;
use App\Models\User;
use App\Models\Post;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Share>
 */
class ShareFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Share::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shareType = $this->faker->randomElement(['repost', 'quote_repost', 'external']);
        $isPrivateShare = $shareType === 'private_share';
        
        return [
            'user_id' => User::factory(),
            'shareable_id' => Post::factory(),
            'shareable_type' => Post::class,
            'share_type' => $shareType,
            'platform' => $shareType === 'external' ? $this->faker->randomElement(['twitter', 'facebook', 'linkedin', 'reddit']) : null,
            'content' => $this->faker->optional(0.4)->paragraph(1),
            'metadata' => null,
            'shared_to_user_id' => null,
            'shared_to_group_id' => null,
            'visibility' => $isPrivateShare ? 'private' : $this->faker->randomElement(['public', 'friends']),
            'is_quote_share' => $this->faker->boolean(30),
            'is_private_share' => $isPrivateShare,
            'shared_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
        ];
    }

    /**
     * Create a repost (internal share without additional content).
     */
    public function repost(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'repost',
            'platform' => null,
            'content' => null,
            'is_quote_share' => false,
        ]);
    }

    /**
     * Create a quote repost (internal share with additional content).
     */
    public function quoteRepost(): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'quote_repost',
            'platform' => null,
            'content' => $this->faker->paragraph(1),
            'is_quote_share' => true,
        ]);
    }

    /**
     * Create an external share to a specific platform.
     */
    public function external(?string $platform = null): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'external',
            'platform' => $platform ?? $this->faker->randomElement(['twitter', 'facebook', 'linkedin', 'reddit']),
            'content' => null,
            'is_quote_share' => false,
        ]);
    }

    /**
     * Create a private share to a specific user.
     */
    public function privateShare(?User $sharedToUser = null): static
    {
        return $this->state(fn (array $attributes) => [
            'share_type' => 'private_share',
            'shared_to_user_id' => $sharedToUser?->id ?? User::factory(),
            'visibility' => 'private',
            'is_private_share' => true,
            'platform' => null,
        ]);
    }

    /**
     * Create a share to a specific group.
     */
    public function toGroup(?Group $group = null): static
    {
        return $this->state(fn (array $attributes) => [
            'shared_to_group_id' => $group?->id ?? Group::factory(),
            'visibility' => 'public',
        ]);
    }

    /**
     * Create a share for a specific post.
     */
    public function forPost(Post $post): static
    {
        return $this->state(fn (array $attributes) => [
            'shareable_id' => $post->id,
            'shareable_type' => Post::class,
        ]);
    }

    /**
     * Create a share by a specific user.
     */
    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a recent share.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'shared_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create an old share.
     */
    public function old(): static
    {
        return $this->state(fn (array $attributes) => [
            'shared_at' => $this->faker->dateTimeBetween('-6 months', '-1 month'),
        ]);
    }

    /**
     * Create a popular share (with content from a popular post).
     */
    public function popular(): static
    {
        return $this->state(fn (array $attributes) => [
            'shareable_id' => Post::factory()->popular(),
            'share_type' => $this->faker->randomElement(['repost', 'quote_repost']),
        ]);
    }

    /**
     * Create a share with custom content.
     */
    public function withContent(string $content): static
    {
        return $this->state(fn (array $attributes) => [
            'content' => $content,
            'is_quote_share' => true,
        ]);
    }

    /**
     * Create multiple shares for the same content.
     */
    public function viral(): static
    {
        return $this->state(fn (array $attributes) => [
            'shared_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }

    /**
     * Configure the model factory with post-generation logic.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Share $share) {
            // Ensure proper quote share flag based on content
            if ($share->content && !$share->is_quote_share) {
                $share->update(['is_quote_share' => true]);
            }

            // Ensure private share flag is set correctly
            if ($share->shared_to_user_id && !$share->is_private_share) {
                $share->update([
                    'is_private_share' => true,
                    'visibility' => 'private',
                ]);
            }
        });
    }
} 