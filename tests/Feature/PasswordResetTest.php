<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\SendPasswordResetJob;
use App\Services\PasswordResetService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

class PasswordResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();
    }

    public function test_forgot_password_queues_reset_email_for_existing_user()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'is_active' => true,
            'is_banned' => false
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset link sent to your email'
        ]);

        Queue::assertPushed(SendPasswordResetJob::class);
    }

    public function test_forgot_password_does_not_reveal_non_existent_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.'
        ]);

        Queue::assertNotPushed(SendPasswordResetJob::class);
    }

    public function test_forgot_password_rate_limiting_by_email()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'is_active' => true,
            'is_banned' => false
        ]);

        // Set up rate limit cache to simulate max attempts reached
        Cache::put('password_reset_email_user@example.com', 10, now()->addDay());

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Too many password reset attempts'
        ]);
    }

    public function test_forgot_password_rate_limiting_by_ip()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'is_active' => true,
            'is_banned' => false
        ]);

        // Set up IP rate limit cache to simulate max attempts reached
        Cache::put('password_reset_ip_127.0.0.1', 3, now()->addHour());

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Too many password reset attempts'
        ]);
    }

    public function test_forgot_password_cooldown_period()
    {
        $user = User::factory()->make([
            'id' => 1,
            'email' => 'user@example.com',
            'is_active' => true,
            'is_banned' => false
        ]);

        // Set up cooldown cache to simulate recent request
        Cache::put('password_reset_sent_1', now(), 300);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'user@example.com'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Please wait before requesting another password reset'
        ]);
    }

    public function test_forgot_password_ignores_banned_user()
    {
        $user = User::factory()->make([
            'email' => 'banned@example.com',
            'is_active' => true,
            'is_banned' => true
        ]);

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'banned@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'If the email exists, a password reset link has been sent.'
        ]);

        Queue::assertNotPushed(SendPasswordResetJob::class);
    }

    public function test_reset_password_with_valid_token()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword')
        ]);

        $token = 'valid-reset-token';
        
        // Mock password reset token in database
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => [
                'tokens_revoked' => true,
                'require_new_login' => true
            ]
        ]);
    }

    public function test_reset_password_with_invalid_token()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com'
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'invalid-token',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or expired reset token'
        ]);
    }

    public function test_reset_password_with_expired_token()
    {
        $user = User::factory()->make([
            'email' => 'user@example.com'
        ]);

        $token = 'expired-token';
        
        // Mock expired password reset token
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now()->subHours(2), // Expired (older than 1 hour)
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Reset token has expired'
        ]);
    }

    public function test_reset_password_rejects_same_password()
    {
        $currentPassword = 'SamePassword123!';
        $user = User::factory()->make([
            'email' => 'user@example.com',
            'password' => Hash::make($currentPassword)
        ]);

        $token = 'valid-token';
        
        DB::table('password_reset_tokens')->insert([
            'email' => 'user@example.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => $token,
            'password' => $currentPassword,
            'password_confirmation' => $currentPassword
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'New password must be different from current password'
        ]);
    }

    public function test_reset_password_for_non_existent_user()
    {
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'nonexistent@example.com',
            'token' => 'any-token',
            'password' => 'NewStrongPassword123!',
            'password_confirmation' => 'NewStrongPassword123!'
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid or expired reset token'
        ]);
    }

    public function test_password_reset_service_generates_secure_token()
    {
        $service = new PasswordResetService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateSecureToken');
        $method->setAccessible(true);
        
        $token1 = $method->invoke($service);
        $token2 = $method->invoke($service);
        
        $this->assertNotEquals($token1, $token2);
        $this->assertEquals(64, strlen($token1));
        $this->assertEquals(64, strlen($token2));
    }

    public function test_password_reset_validation_rules()
    {
        // Test missing email
        $response = $this->postJson('/api/auth/forgot-password', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        // Test invalid email format
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);

        // Test missing fields for reset
        $response = $this->postJson('/api/auth/reset-password', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'token', 'password']);

        // Test password confirmation mismatch
        $response = $this->postJson('/api/auth/reset-password', [
            'email' => 'user@example.com',
            'token' => 'some-token',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'DifferentPassword123!'
        ]);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
} 