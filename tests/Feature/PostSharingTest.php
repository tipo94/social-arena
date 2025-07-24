<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Share;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class PostSharingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->post = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Test post for sharing',
            'visibility' => 'public',
            'allow_resharing' => true,
        ]);
    }

    public function test_can_repost_content(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'share_id',
                    'share_type',
                    'shares_count',
                    'shared_at',
                ]
            ]);

        $this->assertDatabaseHas('shares', [
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'repost',
        ]);

        // Check post share count updated
        $this->post->refresh();
        $this->assertEquals(1, $this->post->shares_count);
    }

    public function test_can_quote_repost_with_content(): void
    {
        Sanctum::actingAs($this->user);

        $quoteContent = "This is an interesting perspective!";

        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'quote_repost',
            'content' => $quoteContent,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('shares', [
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'quote_repost',
            'content' => $quoteContent,
            'is_quote_share' => true,
        ]);
    }

    public function test_can_share_externally_to_platform(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'external',
            'platform' => 'twitter',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.platform', 'twitter')
            ->assertJsonPath('data.share_type', 'external');

        $this->assertDatabaseHas('shares', [
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'share_type' => 'external',
            'platform' => 'twitter',
        ]);
    }

    public function test_can_share_privately_to_user(): void
    {
        Sanctum::actingAs($this->user);

        $targetUser = User::factory()->create();

        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'private_share',
            'shared_to_user_id' => $targetUser->id,
            'content' => 'Check this out!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('shares', [
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'share_type' => 'private_share',
            'shared_to_user_id' => $targetUser->id,
            'is_private_share' => true,
            'visibility' => 'private',
        ]);
    }

    public function test_cannot_repost_own_content(): void
    {
        Sanctum::actingAs($this->user);

        $ownPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'allow_resharing' => true,
        ]);

        $response = $this->postJson("/api/posts/{$ownPost->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You cannot repost your own content',
            ]);
    }

    public function test_can_share_own_content_externally(): void
    {
        Sanctum::actingAs($this->user);

        $ownPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'allow_resharing' => true,
        ]);

        $response = $this->postJson("/api/posts/{$ownPost->id}/share", [
            'share_type' => 'external',
            'platform' => 'facebook',
        ]);

        $response->assertStatus(200);
    }

    public function test_cannot_share_post_that_disallows_resharing(): void
    {
        Sanctum::actingAs($this->user);

        $this->post->update(['allow_resharing' => false]);

        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'This post cannot be shared',
            ]);
    }

    public function test_cannot_share_inaccessible_post(): void
    {
        Sanctum::actingAs($this->user);

        $privatePost = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'visibility' => 'private',
        ]);

        $response = $this->postJson("/api/posts/{$privatePost->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Post not found or not accessible',
            ]);
    }

    public function test_cannot_create_duplicate_share(): void
    {
        Sanctum::actingAs($this->user);

        // First share
        Share::factory()->create([
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'repost',
        ]);

        // Attempt duplicate share
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'You have already shared this post',
            ]);
    }

    public function test_can_create_different_share_types_for_same_post(): void
    {
        Sanctum::actingAs($this->user);

        // Create repost
        $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ])->assertStatus(200);

        // Create external share (should be allowed)
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'external',
            'platform' => 'twitter',
        ]);

        $response->assertStatus(200);

        // Verify both shares exist
        $this->assertDatabaseCount('shares', 2);
    }

    public function test_can_get_shares_for_post(): void
    {
        Sanctum::actingAs($this->user);

        // Create multiple shares
        Share::factory()->count(3)->create([
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/shares");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'shares' => [
                        '*' => [
                            'id',
                            'user' => [
                                'id',
                                'name',
                                'username',
                            ],
                            'share_type',
                            'shared_at',
                        ]
                    ],
                    'pagination',
                    'type_filter',
                ]
            ]);

        $this->assertEquals(3, count($response->json('data.shares')));
    }

    public function test_can_filter_shares_by_type(): void
    {
        Sanctum::actingAs($this->user);

        // Create different types of shares
        Share::factory()->repost()->create([
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
        ]);

        Share::factory()->external('twitter')->create([
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
        ]);

        Share::factory()->quoteRepost()->create([
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
        ]);

        // Test filtering by reposts
        $response = $this->getJson("/api/posts/{$this->post->id}/shares?type=reposts");
        $shares = $response->json('data.shares');
        $this->assertEquals(2, count($shares)); // repost + quote_repost

        // Test filtering by external
        $response = $this->getJson("/api/posts/{$this->post->id}/shares?type=external");
        $shares = $response->json('data.shares');
        $this->assertEquals(1, count($shares));

        // Test filtering by quote shares
        $response = $this->getJson("/api/posts/{$this->post->id}/shares?type=quote_shares");
        $shares = $response->json('data.shares');
        $this->assertEquals(1, count($shares));
    }

    public function test_can_unshare_post(): void
    {
        Sanctum::actingAs($this->user);

        // Create a share first
        $share = Share::factory()->create([
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'repost',
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/unshare", [
            'share_id' => $share->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Share removed successfully',
            ]);

        $this->assertDatabaseMissing('shares', [
            'id' => $share->id,
        ]);

        // Check share count decremented
        $this->post->refresh();
        $this->assertEquals(0, $this->post->shares_count);
    }

    public function test_can_unshare_by_type(): void
    {
        Sanctum::actingAs($this->user);

        $share = Share::factory()->create([
            'user_id' => $this->user->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'repost',
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/unshare", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('shares', [
            'id' => $share->id,
        ]);
    }

    public function test_cannot_unshare_non_existent_share(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/unshare", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Share not found',
            ]);
    }

    public function test_cannot_unshare_others_share(): void
    {
        Sanctum::actingAs($this->user);

        $share = Share::factory()->create([
            'user_id' => $this->otherUser->id,
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
            'share_type' => 'repost',
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/unshare", [
            'share_id' => $share->id,
        ]);

        $response->assertStatus(404); // Should not find it because it's not theirs
    }

    public function test_share_validation_rules(): void
    {
        Sanctum::actingAs($this->user);

        // Test invalid share type
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'invalid_type',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['share_type']);

        // Test invalid platform
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'platform' => 'invalid_platform',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform']);

        // Test content too long
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'content' => str_repeat('a', 501),
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        // Test invalid user ID
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'shared_to_user_id' => 99999,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shared_to_user_id']);
    }

    public function test_unauthenticated_user_cannot_share(): void
    {
        $response = $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ]);

        $response->assertStatus(401);
    }

    public function test_share_count_updates_correctly(): void
    {
        Sanctum::actingAs($this->user);

        $initialCount = $this->post->shares_count;

        // Create share
        $this->postJson("/api/posts/{$this->post->id}/share", [
            'share_type' => 'repost',
        ]);

        $this->post->refresh();
        $this->assertEquals($initialCount + 1, $this->post->shares_count);

        // Delete share
        $this->deleteJson("/api/posts/{$this->post->id}/unshare", [
            'share_type' => 'repost',
        ]);

        $this->post->refresh();
        $this->assertEquals($initialCount, $this->post->shares_count);
    }

    public function test_shares_pagination_works(): void
    {
        Sanctum::actingAs($this->user);

        // Create 25 shares
        Share::factory()->count(25)->create([
            'shareable_id' => $this->post->id,
            'shareable_type' => Post::class,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/shares?per_page=10");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(10, count($data['shares']));
        $this->assertEquals(3, $data['pagination']['last_page']);
        $this->assertTrue($data['pagination']['has_more_pages']);
    }
} 