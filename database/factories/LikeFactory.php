<?php

namespace Database\Factories;

use App\Models\Like;
use App\Models\User;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Like>
 */
class LikeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Like::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'likeable_id' => Post::factory(),
            'likeable_type' => Post::class,
            'type' => $this->faker->randomElement(['like', 'love', 'laugh', 'angry', 'sad', 'wow']),
            'created_at' => $this->faker->dateTimeBetween('-1 year', 'now'),
        ];
    }

    /**
     * Indicate that the like is of type "like".
     */
    public function like(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'like',
        ]);
    }

    /**
     * Indicate that the like is of type "love".
     */
    public function love(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'love',
        ]);
    }

    /**
     * Indicate that the like is of type "laugh".
     */
    public function laugh(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'laugh',
        ]);
    }

    /**
     * Indicate that the like is of type "wow".
     */
    public function wow(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'wow',
        ]);
    }

    /**
     * Indicate that the like is of type "angry".
     */
    public function angry(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'angry',
        ]);
    }

    /**
     * Indicate that the like is of type "sad".
     */
    public function sad(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'sad',
        ]);
    }

    /**
     * Indicate that the like is for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Indicate that the like is for a specific post.
     */
    public function forPost(Post $post): static
    {
        return $this->state(fn (array $attributes) => [
            'likeable_id' => $post->id,
            'likeable_type' => Post::class,
        ]);
    }

    /**
     * Indicate that the like is recent (within last 24 hours).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
} 