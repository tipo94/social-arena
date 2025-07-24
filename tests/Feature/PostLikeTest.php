<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class PostLikeTest extends TestCase
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
            'content' => 'Test post content',
            'visibility' => 'public',
            'allow_reactions' => true,
        ]);
    }

    public function test_authenticated_user_can_like_a_post(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/posts/{$this->post->id}/like");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Post liked',
            'data' => [
                'liked' => true,
                'likes_count' => 1,
            ],
        ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
            'type' => 'like',
        ]);

        $this->post->refresh();
        $this->assertEquals(1, $this->post->likes_count);
    }

    public function test_authenticated_user_can_unlike_a_post(): void
    {
        Sanctum::actingAs($this->user);

        // First like the post
        $this->post->likes()->create([
            'user_id' => $this->user->id,
            'type' => 'like',
        ]);
        $this->post->increment('likes_count');

        // Then unlike it
        $response = $this->postJson("/api/posts/{$this->post->id}/like");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Post unliked',
            'data' => [
                'liked' => false,
                'likes_count' => 0,
            ],
        ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
        ]);

        $this->post->refresh();
        $this->assertEquals(0, $this->post->likes_count);
    }

    public function test_unauthenticated_user_cannot_like_a_post(): void
    {
        $response = $this->postJson("/api/posts/{$this->post->id}/like");

        $response->assertStatus(401);
    }

    public function test_user_cannot_like_non_existent_post(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/posts/999999/like');

        $response->assertStatus(404);
    }

    public function test_user_cannot_like_hidden_post(): void
    {
        $hiddenPost = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Hidden post content',
            'visibility' => 'private',
            'allow_reactions' => true,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/posts/{$hiddenPost->id}/like");

        $response->assertStatus(404);
    }

    public function test_user_cannot_like_post_with_reactions_disabled(): void
    {
        $postWithoutReactions = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'content' => 'Post without reactions',
            'visibility' => 'public',
            'allow_reactions' => false,
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->postJson("/api/posts/{$postWithoutReactions->id}/like");

        $response->assertStatus(403);
    }

    public function test_user_can_like_their_own_post(): void
    {
        Sanctum::actingAs($this->user);

        $myPost = Post::factory()->create([
            'user_id' => $this->user->id,
            'content' => 'My own post',
            'visibility' => 'public',
            'allow_reactions' => true,
        ]);

        $response = $this->postJson("/api/posts/{$myPost->id}/like");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Post liked',
            'data' => [
                'liked' => true,
                'likes_count' => 1,
            ],
        ]);
    }

    public function test_multiple_users_can_like_same_post(): void
    {
        $user3 = User::factory()->create();
        
        // User 1 likes the post
        Sanctum::actingAs($this->user);
        $this->postJson("/api/posts/{$this->post->id}/like");

        // User 3 likes the post
        Sanctum::actingAs($user3);
        $this->postJson("/api/posts/{$this->post->id}/like");

        $this->post->refresh();
        $this->assertEquals(2, $this->post->likes_count);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
        ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $user3->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
        ]);
    }

    public function test_like_model_relationships(): void
    {
        $like = Like::factory()->create([
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
            'type' => 'like',
        ]);

        // Test user relationship
        $this->assertInstanceOf(User::class, $like->user);
        $this->assertEquals($this->user->id, $like->user->id);

        // Test likeable relationship
        $this->assertInstanceOf(Post::class, $like->likeable);
        $this->assertEquals($this->post->id, $like->likeable->id);
    }

    public function test_post_likes_relationship(): void
    {
        // Create multiple likes for the post
        Like::factory()->count(3)->create([
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
            'type' => 'like',
        ]);

        $this->assertEquals(3, $this->post->likes()->count());
        $this->assertInstanceOf(Like::class, $this->post->likes()->first());
    }

    public function test_like_types_are_supported(): void
    {
        Sanctum::actingAs($this->user);

        $like = $this->post->likes()->create([
            'user_id' => $this->user->id,
            'type' => 'love',
        ]);

        $this->assertEquals('love', $like->type);
        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $this->post->id,
            'likeable_type' => Post::class,
            'type' => 'love',
        ]);
    }

    public function test_user_cannot_like_same_post_twice(): void
    {
        // Create a unique constraint test by ensuring database prevents duplicate likes
        $this->post->likes()->create([
            'user_id' => $this->user->id,
            'type' => 'like',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        
        $this->post->likes()->create([
            'user_id' => $this->user->id,
            'type' => 'love', // Even with different type, should fail due to unique constraint
        ]);
    }
} 