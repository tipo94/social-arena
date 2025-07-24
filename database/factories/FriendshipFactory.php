<?php

namespace Database\Factories;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Friendship::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestedAt = $this->faker->dateTimeBetween('-6 months', 'now');
        $status = $this->faker->randomElement(['pending', 'accepted', 'blocked', 'declined']);
        
        return [
            'user_id' => User::factory(),
            'friend_id' => User::factory(),
            'status' => $status,
            'requested_at' => $requestedAt,
            'accepted_at' => $status === 'accepted' ? 
                $this->faker->dateTimeBetween($requestedAt, 'now') : null,
            'blocked_at' => $status === 'blocked' ? 
                $this->faker->dateTimeBetween($requestedAt, 'now') : null,
            'can_see_posts' => $this->faker->boolean(85), // 85% allow post visibility
            'can_send_messages' => $this->faker->boolean(90), // 90% allow messages
            'show_in_friends_list' => $this->faker->boolean(80), // 80% show in friends list
            'mutual_friends_count' => $status === 'accepted' ? 
                $this->faker->numberBetween(0, 50) : 0,
        ];
    }

    /**
     * Create a pending friendship request.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_PENDING,
            'accepted_at' => null,
            'blocked_at' => null,
            'mutual_friends_count' => 0,
        ]);
    }

    /**
     * Create an accepted friendship.
     */
    public function accepted(): static
    {
        return $this->state(function (array $attributes) {
            $requestedAt = $attributes['requested_at'] ?? $this->faker->dateTimeBetween('-6 months', '-1 day');
            
            return [
                'status' => Friendship::STATUS_ACCEPTED,
                'requested_at' => $requestedAt,
                'accepted_at' => $this->faker->dateTimeBetween($requestedAt, 'now'),
                'blocked_at' => null,
                'mutual_friends_count' => $this->faker->numberBetween(0, 50),
            ];
        });
    }

    /**
     * Create a blocked friendship.
     */
    public function blocked(): static
    {
        return $this->state(function (array $attributes) {
            $requestedAt = $attributes['requested_at'] ?? $this->faker->dateTimeBetween('-6 months', '-1 day');
            
            return [
                'status' => Friendship::STATUS_BLOCKED,
                'requested_at' => $requestedAt,
                'accepted_at' => null,
                'blocked_at' => $this->faker->dateTimeBetween($requestedAt, 'now'),
                'mutual_friends_count' => 0,
                'can_see_posts' => false,
                'can_send_messages' => false,
            ];
        });
    }

    /**
     * Create a declined friendship.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_DECLINED,
            'accepted_at' => null,
            'blocked_at' => null,
            'mutual_friends_count' => 0,
        ]);
    }

    /**
     * Create a friendship between specific users.
     */
    public function between(User $user1, User $user2): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user1->id,
            'friend_id' => $user2->id,
        ]);
    }

    /**
     * Create a friendship sent by a specific user.
     */
    public function sentBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'friend_id' => User::factory(),
        ]);
    }

    /**
     * Create a friendship received by a specific user.
     */
    public function receivedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => User::factory(),
            'friend_id' => $user->id,
        ]);
    }

    /**
     * Create a recent friendship.
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            $requestedAt = $this->faker->dateTimeBetween('-7 days', 'now');
            
            return [
                'requested_at' => $requestedAt,
                'accepted_at' => $attributes['status'] === 'accepted' ? 
                    $this->faker->dateTimeBetween($requestedAt, 'now') : null,
            ];
        });
    }

    /**
     * Create an old friendship.
     */
    public function old(): static
    {
        return $this->state(function (array $attributes) {
            $requestedAt = $this->faker->dateTimeBetween('-2 years', '-1 year');
            
            return [
                'requested_at' => $requestedAt,
                'accepted_at' => $attributes['status'] === 'accepted' ? 
                    $this->faker->dateTimeBetween($requestedAt, '-1 year') : null,
            ];
        });
    }

    /**
     * Create a friendship with high mutual friends count.
     */
    public function withHighMutualFriends(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'mutual_friends_count' => $this->faker->numberBetween(20, 100),
        ]);
    }

    /**
     * Create a friendship with low mutual friends count.
     */
    public function withLowMutualFriends(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'mutual_friends_count' => $this->faker->numberBetween(0, 5),
        ]);
    }

    /**
     * Create a friendship that allows all interactions.
     */
    public function allowAllInteractions(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_see_posts' => true,
            'can_send_messages' => true,
            'show_in_friends_list' => true,
        ]);
    }

    /**
     * Create a friendship with restricted interactions.
     */
    public function restrictedInteractions(): static
    {
        return $this->state(fn (array $attributes) => [
            'can_see_posts' => false,
            'can_send_messages' => false,
            'show_in_friends_list' => false,
        ]);
    }

    /**
     * Create a close friendship (high interaction permissions).
     */
    public function close(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'can_see_posts' => true,
            'can_send_messages' => true,
            'show_in_friends_list' => true,
            'mutual_friends_count' => $this->faker->numberBetween(10, 50),
        ]);
    }

    /**
     * Create a distant friendship (limited interaction permissions).
     */
    public function distant(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Friendship::STATUS_ACCEPTED,
            'can_see_posts' => $this->faker->boolean(50),
            'can_send_messages' => $this->faker->boolean(30),
            'show_in_friends_list' => $this->faker->boolean(70),
            'mutual_friends_count' => $this->faker->numberBetween(0, 5),
        ]);
    }

    /**
     * Create a popular user friendship (user with many friends).
     */
    public function withPopularUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'mutual_friends_count' => $this->faker->numberBetween(25, 100),
        ]);
    }

    /**
     * Configure the model factory with post-generation logic.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Friendship $friendship) {
            // Ensure mutual friends count is 0 for non-accepted friendships
            if ($friendship->status !== Friendship::STATUS_ACCEPTED) {
                $friendship->update(['mutual_friends_count' => 0]);
            }

            // Ensure proper timestamp consistency
            if ($friendship->status === Friendship::STATUS_ACCEPTED && !$friendship->accepted_at) {
                $friendship->update(['accepted_at' => now()]);
            }

            if ($friendship->status === Friendship::STATUS_BLOCKED && !$friendship->blocked_at) {
                $friendship->update(['blocked_at' => now()]);
            }

            // Ensure blocked friendships have restricted permissions
            if ($friendship->status === Friendship::STATUS_BLOCKED) {
                $friendship->update([
                    'can_see_posts' => false,
                    'can_send_messages' => false,
                ]);
            }
        });
    }
} 