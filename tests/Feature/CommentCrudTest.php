<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Like;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CommentCrudTest extends TestCase
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
            'content' => 'Test post for comments',
            'visibility' => 'public',
            'allow_comments' => true,
        ]);
    }

    public function test_can_get_comments_for_post(): void
    {
        Sanctum::actingAs($this->user);

        // Create some comments
        $comments = Comment::factory()->count(3)->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/comments");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'comments' => [
                        '*' => [
                            'id',
                            'user',
                            'content',
                            'likes_count',
                            'replies_count',
                            'created_at',
                        ]
                    ],
                    'pagination',
                    'sort',
                ]
            ]);

        $this->assertEquals(3, count($response->json('data.comments')));
    }

    public function test_can_create_comment(): void
    {
        Sanctum::actingAs($this->user);

        $commentData = [
            'content' => 'This is a test comment',
            'type' => 'text',
        ];

        $response = $this->postJson("/api/posts/{$this->post->id}/comments", $commentData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'user',
                    'content',
                    'type',
                    'likes_count',
                    'replies_count',
                    'created_at',
                ]
            ]);

        $this->assertDatabaseHas('comments', [
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'content' => '<p>' . $commentData['content'] . '</p>',
            'type' => 'text',
        ]);

        // Check post comment count updated
        $this->post->refresh();
        $this->assertEquals(1, $this->post->comments_count);
    }

    public function test_can_create_reply_comment(): void
    {
        Sanctum::actingAs($this->user);

        // Create parent comment
        $parentComment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        $replyData = [
            'content' => 'This is a reply',
            'parent_id' => $parentComment->id,
        ];

        $response = $this->postJson("/api/posts/{$this->post->id}/comments", $replyData);

        $response->assertStatus(201);

        $this->assertDatabaseHas('comments', [
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'parent_id' => $parentComment->id,
            'content' => '<p>' . $replyData['content'] . '</p>',
            'depth' => 1,
        ]);

        // Check parent comment replies count updated
        $parentComment->refresh();
        $this->assertEquals(1, $parentComment->replies_count);
    }

    public function test_comment_creation_validation(): void
    {
        Sanctum::actingAs($this->user);

        // Test empty content
        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => '',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        // Test too long content
        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => str_repeat('a', 2001),
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);

        // Test invalid parent_id
        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => 'Valid content',
            'parent_id' => 99999,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        // Test invalid type
        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => 'Valid content',
            'type' => 'invalid',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_cannot_comment_on_post_that_disallows_comments(): void
    {
        Sanctum::actingAs($this->user);

        $this->post->update(['allow_comments' => false]);

        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => 'This should fail',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Comments are not allowed on this post',
            ]);
    }

    public function test_cannot_comment_on_inaccessible_post(): void
    {
        Sanctum::actingAs($this->user);

        // Create private post
        $privatePost = Post::factory()->create([
            'user_id' => $this->otherUser->id,
            'visibility' => 'private',
        ]);

        $response = $this->postJson("/api/posts/{$privatePost->id}/comments", [
            'content' => 'This should fail',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Post not found or not accessible',
            ]);
    }

    public function test_can_get_specific_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user',
                    'content',
                    'likes_count',
                    'replies_count',
                    'created_at',
                ]
            ]);

        $this->assertEquals($comment->id, $response->json('data.id'));
    }

    public function test_can_update_own_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'content' => 'Original content',
            'created_at' => now()->subMinutes(5), // Within edit window
        ]);

        $updateData = [
            'content' => 'Updated content',
        ];

        $response = $this->putJson("/api/posts/{$this->post->id}/comments/{$comment->id}", $updateData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment updated successfully',
            ]);

        $this->assertDatabaseHas('comments', [
            'id' => $comment->id,
            'content' => '<p>' . $updateData['content'] . '</p>',
        ]);
    }

    public function test_cannot_update_comment_after_edit_window(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subHours(2), // Outside edit window
        ]);

        $response = $this->putJson("/api/posts/{$this->post->id}/comments/{$comment->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to edit this comment',
            ]);
    }

    public function test_cannot_update_other_users_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->putJson("/api/posts/{$this->post->id}/comments/{$comment->id}", [
            'content' => 'Updated content',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to edit this comment',
            ]);
    }

    public function test_post_owner_can_edit_comments_on_their_post(): void
    {
        Sanctum::actingAs($this->otherUser); // Post owner

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subHours(2), // Outside normal edit window
        ]);

        $response = $this->putJson("/api/posts/{$this->post->id}/comments/{$comment->id}", [
            'content' => 'Updated by post owner',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_delete_own_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/comments/{$comment->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment deleted successfully',
            ]);

        $this->assertSoftDeleted('comments', [
            'id' => $comment->id,
        ]);

        // Check post comment count updated
        $this->post->refresh();
        $this->assertEquals(0, $this->post->comments_count);
    }

    public function test_can_delete_comment_with_reason(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/comments/{$comment->id}", [
            'reason' => 'Inappropriate content',
        ]);

        $response->assertStatus(200);

        $comment->refresh();
        $this->assertNotNull($comment->moderated_at);
        $this->assertEquals($this->user->id, $comment->moderated_by);
    }

    public function test_cannot_delete_other_users_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/comments/{$comment->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'You do not have permission to delete this comment',
            ]);
    }

    public function test_post_owner_can_delete_comments_on_their_post(): void
    {
        Sanctum::actingAs($this->otherUser); // Post owner

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/posts/{$this->post->id}/comments/{$comment->id}");

        $response->assertStatus(200);
    }

    public function test_can_like_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/comments/{$comment->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'comment_id' => $comment->id,
                    'likes_count' => 1,
                    'is_liked_by_user' => true,
                    'action' => 'liked',
                ],
            ]);

        $this->assertDatabaseHas('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $comment->id,
            'likeable_type' => Comment::class,
        ]);

        $comment->refresh();
        $this->assertEquals(1, $comment->likes_count);
    }

    public function test_can_unlike_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        // First like the comment
        Like::create([
            'user_id' => $this->user->id,
            'likeable_id' => $comment->id,
            'likeable_type' => Comment::class,
            'type' => 'like',
        ]);
        $comment->increment('likes_count');

        // Now unlike it
        $response = $this->postJson("/api/posts/{$this->post->id}/comments/{$comment->id}/like");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'action' => 'unliked',
                    'is_liked_by_user' => false,
                ],
            ]);

        $this->assertDatabaseMissing('likes', [
            'user_id' => $this->user->id,
            'likeable_id' => $comment->id,
            'likeable_type' => Comment::class,
        ]);

        $comment->refresh();
        $this->assertEquals(0, $comment->likes_count);
    }

    public function test_can_report_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/comments/{$comment->id}/report", [
            'reason' => 'Inappropriate content',
            'category' => 'inappropriate',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Comment reported successfully. Our moderation team will review it.',
            ]);

        $comment->refresh();
        $this->assertTrue($comment->is_reported);
        $this->assertNotNull($comment->moderated_at);
    }

    public function test_cannot_report_already_reported_comment(): void
    {
        Sanctum::actingAs($this->user);

        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->otherUser->id,
            'is_reported' => true,
        ]);

        $response = $this->postJson("/api/posts/{$this->post->id}/comments/{$comment->id}/report", [
            'reason' => 'Inappropriate content',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This comment has already been reported',
            ]);
    }

    public function test_can_get_comment_replies(): void
    {
        Sanctum::actingAs($this->user);

        $parentComment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $replies = Comment::factory()->count(3)->create([
            'post_id' => $this->post->id,
            'parent_id' => $parentComment->id,
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/comments/{$parentComment->id}/replies");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'replies' => [
                        '*' => [
                            'id',
                            'user',
                            'content',
                            'parent_id',
                        ]
                    ],
                    'pagination',
                    'parent_comment_id',
                ]
            ]);

        $this->assertEquals(3, count($response->json('data.replies')));
        $this->assertEquals($parentComment->id, $response->json('data.parent_comment_id'));
    }

    public function test_comments_pagination_works(): void
    {
        Sanctum::actingAs($this->user);

        // Create 25 comments
        Comment::factory()->count(25)->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/posts/{$this->post->id}/comments?per_page=10");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals(10, count($data['comments']));
        $this->assertEquals(3, $data['pagination']['last_page']);
        $this->assertTrue($data['pagination']['has_more_pages']);
    }

    public function test_comments_sorting_works(): void
    {
        Sanctum::actingAs($this->user);

        // Create comments with different timestamps
        $oldComment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(2),
        ]);

        $newComment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        // Test newest first (default)
        $response = $this->getJson("/api/posts/{$this->post->id}/comments?sort=newest");
        $comments = $response->json('data.comments');
        $this->assertEquals($newComment->id, $comments[0]['id']);

        // Test oldest first
        $response = $this->getJson("/api/posts/{$this->post->id}/comments?sort=oldest");
        $comments = $response->json('data.comments');
        $this->assertEquals($oldComment->id, $comments[0]['id']);
    }

    public function test_unauthenticated_user_cannot_perform_comment_actions(): void
    {
        $comment = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        // Test create
        $response = $this->postJson("/api/posts/{$this->post->id}/comments", [
            'content' => 'Test comment',
        ]);
        $response->assertStatus(401);

        // Test update
        $response = $this->putJson("/api/posts/{$this->post->id}/comments/{$comment->id}", [
            'content' => 'Updated',
        ]);
        $response->assertStatus(401);

        // Test delete
        $response = $this->deleteJson("/api/posts/{$this->post->id}/comments/{$comment->id}");
        $response->assertStatus(401);

        // Test like
        $response = $this->postJson("/api/posts/{$this->post->id}/comments/{$comment->id}/like");
        $response->assertStatus(401);
    }
} 