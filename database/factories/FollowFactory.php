<?php

namespace Database\Factories;

use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Follow>
 */
class FollowFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Follow::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'follower_id' => User::factory(),
            'following_id' => User::factory(),
            'followed_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'is_muted' => $this->faker->boolean(10), // 10% chance of being muted
            'show_notifications' => $this->faker->boolean(90), // 90% chance of notifications on
            'is_close_friend' => $this->faker->boolean(20), // 20% chance of being close friend
            'interaction_preferences' => $this->faker->optional(0.3)->randomElement([
                ['likes' => true, 'comments' => true, 'shares' => false],
                ['likes' => true, 'comments' => false, 'shares' => true],
                ['likes' => false, 'comments' => true, 'shares' => true],
            ]),
        ];
    }

    /**
     * Create a follow between specific users.
     */
    public function between(User $follower, User $following): static
    {
        return $this->state(function (array $attributes) use ($follower, $following) {
            return [
                'follower_id' => $follower->id,
                'following_id' => $following->id,
            ];
        });
    }

    /**
     * Create a follow by a specific user.
     */
    public function by(User $follower): static
    {
        return $this->state(function (array $attributes) use ($follower) {
            return [
                'follower_id' => $follower->id,
            ];
        });
    }

    /**
     * Create a follow to a specific user.
     */
    public function to(User $following): static
    {
        return $this->state(function (array $attributes) use ($following) {
            return [
                'following_id' => $following->id,
            ];
        });
    }

    /**
     * Create a muted follow.
     */
    public function muted(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_muted' => true,
                'show_notifications' => false,
            ];
        });
    }

    /**
     * Create an active follow.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_muted' => false,
                'show_notifications' => true,
            ];
        });
    }

    /**
     * Create a close friend follow.
     */
    public function closeFriend(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_close_friend' => true,
                'show_notifications' => true,
            ];
        });
    }

    /**
     * Create a follow with notifications disabled.
     */
    public function withoutNotifications(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'show_notifications' => false,
            ];
        });
    }

    /**
     * Create a recent follow.
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'followed_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            ];
        });
    }

    /**
     * Create an old follow.
     */
    public function old(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'followed_at' => $this->faker->dateTimeBetween('-2 years', '-6 months'),
            ];
        });
    }

    /**
     * Create a follow with custom interaction preferences.
     */
    public function withInteractionPreferences(array $preferences): static
    {
        return $this->state(function (array $attributes) use ($preferences) {
            return [
                'interaction_preferences' => $preferences,
            ];
        });
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Follow $follow) {
            // Ensure follower and following are different users
            if ($follow->follower_id === $follow->following_id) {
                $follow->following_id = User::factory()->create()->id;
            }
        });
    }
} 