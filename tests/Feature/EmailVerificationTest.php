<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Jobs\SendEmailVerificationJob;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

class EmailVerificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_user_registration_queues_email_verification()
    {
        $userData = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
            'username' => 'testuser',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201);
        
        // Verify that email verification job was dispatched
        Queue::assertPushed(SendEmailVerificationJob::class, function ($job) {
            return $job->user->email === 'test@example.com';
        });
    }

    public function test_resend_verification_with_rate_limiting()
    {
        $user = User::factory()->make([
            'email_verified_at' => null,
            'email' => 'unverified@example.com'
        ]);
        
        Sanctum::actingAs($user);

        // First request should succeed
        $response = $this->postJson('/api/auth/resend-verification');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['queued', 'rate_limited']
        ]);

        Queue::assertPushed(SendEmailVerificationJob::class);

        // Mock cache to simulate that verification was sent recently
        Cache::put("email_verification_sent_{$user->id}", now(), 300);

        // Second request should be rate limited
        $response = $this->postJson('/api/auth/resend-verification');
        $response->assertStatus(429);
        $response->assertJson([
            'success' => false,
            'message' => 'Please wait before requesting another verification email'
        ]);
    }

    public function test_resend_verification_for_already_verified_user()
    {
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'email' => 'verified@example.com'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/resend-verification');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email already verified'
        ]);

        Queue::assertNotPushed(SendEmailVerificationJob::class);
    }

    public function test_email_verification_with_valid_link()
    {
        $user = User::factory()->make([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        $hash = sha1($user->email);

        $response = $this->getJson("/api/auth/verify-email/{$user->id}/{$hash}");
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Email verified successfully'
        ]);
    }

    public function test_email_verification_with_invalid_hash()
    {
        $user = User::factory()->make([
            'id' => 1,
            'email' => 'test@example.com',
            'email_verified_at' => null
        ]);

        $invalidHash = 'invalid-hash';

        $response = $this->getJson("/api/auth/verify-email/{$user->id}/{$invalidHash}");
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Invalid verification link'
        ]);
    }

    public function test_email_verification_middleware_blocks_unverified_users()
    {
        $user = User::factory()->make([
            'email_verified_at' => null
        ]);
        
        Sanctum::actingAs($user);

        // Create a test route that requires email verification
        $this->app['router']->middleware(['auth:sanctum', 'verified'])
            ->get('/test-verified', function () {
                return response()->json(['message' => 'Access granted']);
            });

        $response = $this->getJson('/test-verified');
        
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Email verification required'
        ]);
    }

    public function test_email_verification_middleware_allows_verified_users()
    {
        $user = User::factory()->make([
            'email_verified_at' => now()
        ]);
        
        Sanctum::actingAs($user);

        // Create a test route that requires email verification
        $this->app['router']->middleware(['auth:sanctum', 'verified'])
            ->get('/test-verified', function () {
                return response()->json(['message' => 'Access granted']);
            });

        $response = $this->getJson('/test-verified');
        
        $response->assertStatus(200);
        $response->assertJson(['message' => 'Access granted']);
    }

    public function test_email_verification_job_handles_non_existent_user()
    {
        $user = User::factory()->make(['id' => 999]);

        $job = new SendEmailVerificationJob($user);
        
        // Should not throw exception and should log appropriately
        $this->expectNotToPerformAssertions();
        $job->handle();
    }

    public function test_email_verification_job_skips_already_verified_user()
    {
        $user = User::factory()->make([
            'email_verified_at' => now()
        ]);

        $job = new SendEmailVerificationJob($user);
        
        // Should not throw exception and should log appropriately
        $this->expectNotToPerformAssertions();
        $job->handle();
    }
} 