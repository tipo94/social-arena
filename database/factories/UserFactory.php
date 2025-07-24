<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        
        return [
            'name' => $firstName . ' ' . $lastName,
            'username' => fake()->unique()->userName(),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional(0.7)->phoneNumber(),
            'email_verified_at' => fake()->optional(0.8)->dateTime(),
            'phone_verified_at' => fake()->optional(0.3)->dateTime(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'last_login_at' => fake()->optional(0.9)->dateTimeBetween('-1 month', 'now'),
            'last_login_ip' => fake()->optional(0.9)->ipv4(),
            'last_activity_at' => fake()->optional(0.8)->dateTimeBetween('-1 week', 'now'),
            'is_online' => fake()->boolean(20), // 20% chance of being online
            'is_active' => fake()->boolean(95), // 95% chance of being active
            'is_banned' => fake()->boolean(2), // 2% chance of being banned
            'banned_until' => null,
            'ban_reason' => null,
            'role' => fake()->randomElement(['user', 'user', 'user', 'user', 'moderator']), // Mostly users
            'permissions' => null,
            'timezone' => fake()->randomElement([
                'UTC', 'America/New_York', 'America/Los_Angeles', 'Europe/London', 
                'Europe/Paris', 'Asia/Tokyo', 'Australia/Sydney'
            ]),
            'locale' => fake()->randomElement(['en', 'en', 'en', 'es', 'fr', 'de', 'ja']), // Mostly English
            'theme' => fake()->randomElement(['light', 'dark', 'auto']),
            'two_factor_enabled' => fake()->boolean(10), // 10% use 2FA
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'login_count' => fake()->numberBetween(1, 500),
            'last_password_change' => fake()->optional(0.6)->dateTimeBetween('-1 year', 'now'),
            'social_provider' => null,
            'social_provider_id' => null,
            'social_avatar_url' => null,
            'account_type' => fake()->randomElement(['free', 'free', 'free', 'premium']), // Mostly free
            'subscription_status' => 'inactive',
            'subscription_expires_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is banned.
     */
    public function banned(?string $reason = null, ?\DateTime $until = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_banned' => true,
            'ban_reason' => $reason ?? 'Terms of service violation',
            'banned_until' => $until,
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'permissions' => ['manage_users', 'manage_content', 'manage_groups'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user is a moderator.
     */
    public function moderator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'moderator',
            'permissions' => ['moderate_content', 'moderate_groups'],
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Indicate that the user has a premium account.
     */
    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'premium',
            'subscription_status' => 'active',
            'subscription_expires_at' => fake()->dateTimeBetween('now', '+1 year'),
        ]);
    }

    /**
     * Indicate that the user signed up via social login.
     */
    public function social(string $provider = 'google'): static
    {
        return $this->state(fn (array $attributes) => [
            'social_provider' => $provider,
            'social_provider_id' => fake()->unique()->numerify('##########'),
            'social_avatar_url' => fake()->imageUrl(200, 200, 'people'),
            'email_verified_at' => now(), // Social accounts are pre-verified
            'password' => null, // No password for social accounts
        ]);
    }

    /**
     * Indicate that the user is online.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_online' => true,
            'last_activity_at' => now(),
            'last_login_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ]);
    }

    /**
     * Indicate that the user has two-factor authentication enabled.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_enabled' => true,
            'two_factor_secret' => encrypt('base32-secret-key'),
            'two_factor_recovery_codes' => encrypt([
                'recovery-code-1',
                'recovery-code-2', 
                'recovery-code-3',
            ]),
        ]);
    }
}
