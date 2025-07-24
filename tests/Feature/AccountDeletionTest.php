<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Friendship;
use App\Services\AccountDeletionService;
use App\Jobs\ProcessAccountDeletionJob;
use App\Jobs\SendAccountDeletionNotificationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_user_can_get_deletion_info()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/account/deletion/info');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'grace_period_days',
                    'what_gets_deleted',
                    'what_happens',
                    'before_deletion',
                    'alternatives',
                ],
            ]);
    }

    public function test_user_can_get_deletion_status_no_request()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/account/deletion/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'deletion_requested' => false,
                    'is_active' => true,
                ],
            ]);
    }

    public function test_user_can_request_account_deletion()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/request', [
            'password' => 'password123',
            'reason' => 'No longer need the account',
            'confirmation' => true,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account deletion has been scheduled',
            ])
            ->assertJsonStructure([
                'data' => [
                    'deletion_requested_at',
                    'will_be_deleted_at',
                    'grace_period_days',
                    'can_cancel_until',
                ],
            ]);

        // Verify user was deactivated
        $user->refresh();
        $this->assertFalse($user->is_active);
        $this->assertNotNull($user->deletion_requested_at);
        $this->assertNotNull($user->will_be_deleted_at);
        $this->assertEquals('No longer need the account', $user->deletion_reason);

        // Verify jobs were dispatched
        Queue::assertPushed(SendAccountDeletionNotificationJob::class);
        Queue::assertPushed(ProcessAccountDeletionJob::class);
    }

    public function test_deletion_request_requires_correct_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/request', [
            'password' => 'wrong_password',
            'confirmation' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Verify user was not affected
        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deletion_requested_at);
    }

    public function test_deletion_request_requires_confirmation()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/request', [
            'password' => 'password123',
            'confirmation' => false,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['confirmation']);
    }

    public function test_cannot_request_deletion_twice()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'deletion_requested_at' => now(),
            'will_be_deleted_at' => now()->addDays(30),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/request', [
            'password' => 'password123',
            'confirmation' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Account deletion already requested',
            ]);
    }

    public function test_user_can_cancel_deletion_request()
    {
        $user = User::factory()->create([
            'deletion_requested_at' => now(),
            'will_be_deleted_at' => now()->addDays(30),
            'deletion_reason' => 'Test reason',
            'is_active' => false,
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/cancel');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account deletion has been cancelled successfully',
            ]);

        // Verify user was reactivated
        $user->refresh();
        $this->assertTrue($user->is_active);
        $this->assertNull($user->deletion_requested_at);
        $this->assertNull($user->will_be_deleted_at);
        $this->assertNull($user->deletion_reason);

        // Verify notification job was dispatched
        Queue::assertPushed(SendAccountDeletionNotificationJob::class);
    }

    public function test_cannot_cancel_deletion_after_grace_period()
    {
        $user = User::factory()->create([
            'deletion_requested_at' => now()->subDays(31),
            'will_be_deleted_at' => now()->subDay(),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deletion/cancel');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Grace period has expired, account deletion cannot be cancelled',
            ]);
    }

    public function test_user_can_get_deletion_status_with_request()
    {
        $deletionDate = now()->addDays(25);
        $user = User::factory()->create([
            'deletion_requested_at' => now()->subDays(5),
            'will_be_deleted_at' => $deletionDate,
            'deletion_reason' => 'Test reason',
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/account/deletion/status');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'deletion_requested' => true,
                    'deletion_reason' => 'Test reason',
                    'can_cancel' => true,
                    'days_remaining' => 25,
                ],
            ]);
    }

    public function test_user_can_export_data()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        
        // Create some test data
        $post = Post::factory()->create(['user_id' => $user->id]);
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/export-data', [
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'export_generated_at',
                    'user_information',
                    'profile_information',
                    'posts',
                    'comments',
                    'friendships',
                ],
            ]);
    }

    public function test_data_export_requires_correct_password()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/export-data', [
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_can_deactivate_account()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deactivate', [
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account has been deactivated successfully',
            ]);

        // Verify user was deactivated
        $user->refresh();
        $this->assertFalse($user->is_active);
        
        // Verify tokens were revoked
        $this->assertEquals(0, $user->tokens()->count());
    }

    public function test_user_can_reactivate_account()
    {
        $user = User::factory()->create([
            'is_active' => false,
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/reactivate');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Account has been reactivated successfully',
            ]);

        // Verify user was reactivated
        $user->refresh();
        $this->assertTrue($user->is_active);
    }

    public function test_cannot_deactivate_if_deletion_requested()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password123'),
            'deletion_requested_at' => now(),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/deactivate', [
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Account is scheduled for deletion. Cancel deletion first.',
            ]);
    }

    public function test_cannot_reactivate_if_deletion_requested()
    {
        $user = User::factory()->create([
            'is_active' => false,
            'deletion_requested_at' => now(),
        ]);
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/account/reactivate');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Cannot reactivate - account is scheduled for deletion',
            ]);
    }

    public function test_account_deletion_service_deletes_all_user_data()
    {
        $user = User::factory()->create();
        
        // Create related data
        $post = Post::factory()->create(['user_id' => $user->id]);
        $comment = Comment::factory()->create(['user_id' => $user->id]);
        $like = Like::factory()->create(['user_id' => $user->id]);
        $friendship = Friendship::factory()->create(['user_id' => $user->id]);

        $deletionService = app(AccountDeletionService::class);
        $result = $deletionService->permanentlyDeleteAccount($user);

        $this->assertTrue($result['success']);

        // Verify all data was deleted
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
        $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
        $this->assertDatabaseMissing('likes', ['id' => $like->id]);
        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_deletion_endpoints_require_authentication()
    {
        $endpoints = [
            ['GET', '/api/users/account/deletion/info'],
            ['GET', '/api/users/account/deletion/status'],
            ['POST', '/api/users/account/deletion/request'],
            ['POST', '/api/users/account/deletion/cancel'],
            ['POST', '/api/users/account/export-data'],
            ['POST', '/api/users/account/deactivate'],
            ['POST', '/api/users/account/reactivate'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertStatus(401);
        }
    }

    public function test_deletion_job_skips_if_user_cancelled()
    {
        $user = User::factory()->create([
            'deletion_requested_at' => null, // Deletion was cancelled
        ]);

        $job = new ProcessAccountDeletionJob($user);
        $job->handle();

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_deletion_job_skips_if_too_early()
    {
        $user = User::factory()->create([
            'deletion_requested_at' => now(),
            'will_be_deleted_at' => now()->addDays(5), // Still in future
        ]);

        $job = new ProcessAccountDeletionJob($user);
        $job->handle();

        // User should still exist
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }
} 