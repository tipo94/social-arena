<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class CommentNestedRepliesTest extends TestCase
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

    public function test_can_create_root_comment(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'content' => 'This is a root comment',
            'parent_id' => null,
        ]);

        $this->assertEquals(0, $comment->depth);
        $this->assertTrue($comment->isRoot());
        $this->assertNull($comment->parent_id);
        $this->assertEquals((string) $comment->id, $comment->path);
    }

    public function test_can_create_reply_to_comment(): void
    {
        $rootComment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'content' => 'Root comment',
        ]);

        $reply = Comment::factory()->create([
            'user_id' => $this->otherUser->id,
            'post_id' => $this->post->id,
            'parent_id' => $rootComment->id,
            'content' => 'Reply to root comment',
        ]);

        $this->assertEquals(1, $reply->depth);
        $this->assertFalse($reply->isRoot());
        $this->assertEquals($rootComment->id, $reply->parent_id);
        $this->assertStringContainsString((string) $rootComment->id, $reply->path);
        
        $rootComment->refresh();
        $this->assertEquals(1, $rootComment->replies_count);
    }

    public function test_can_create_nested_replies_up_to_max_depth(): void
    {
        $comments = [];
        $comments[0] = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'content' => 'Root comment',
        ]);

        // Create replies up to MAX_DEPTH
        for ($depth = 1; $depth <= Comment::MAX_DEPTH; $depth++) {
            $comments[$depth] = Comment::factory()->create([
                'user_id' => $this->user->id,
                'post_id' => $this->post->id,
                'parent_id' => $comments[$depth - 1]->id,
                'content' => "Reply at depth {$depth}",
            ]);

            $this->assertEquals($depth, $comments[$depth]->depth);
            $this->assertEquals($comments[$depth - 1]->id, $comments[$depth]->parent_id);
        }
    }

    public function test_cannot_exceed_max_depth(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum nesting depth exceeded');

        // Create a chain of comments up to MAX_DEPTH manually
        $current = Comment::factory()->create([
            'post_id' => $this->post->id,
        ]);

        // Create comments up to MAX_DEPTH (since we start at depth 0)
        for ($depth = 1; $depth <= Comment::MAX_DEPTH; $depth++) {
            $current = Comment::factory()->create([
                'post_id' => $this->post->id,
                'parent_id' => $current->id,
            ]);
        }

        // Now current is at depth MAX_DEPTH, trying to create one more should fail
        $this->assertEquals(Comment::MAX_DEPTH, $current->fresh()->depth);
        Comment::factory()->create([
            'post_id' => $this->post->id,
            'parent_id' => $current->id,
        ]);
    }

    public function test_comment_relationships_work_correctly(): void
    {
        $root = Comment::factory()->create([
            'post_id' => $this->post->id,
            'user_id' => $this->user->id,
        ]);

        $reply1 = Comment::factory()->reply($root)->create();
        $reply2 = Comment::factory()->reply($root)->create();
        $nestedReply = Comment::factory()->reply($reply1)->create();

        // Test parent-child relationships
        $this->assertEquals($root->id, $reply1->parent->id);
        $this->assertEquals($root->id, $reply2->parent->id);
        $this->assertEquals($reply1->id, $nestedReply->parent->id);

        // Test replies relationship
        $this->assertEquals(2, $root->replies()->count());
        $this->assertEquals(1, $reply1->replies()->count());
        $this->assertEquals(0, $reply2->replies()->count());

        // Test descendants  
        $descendants = $root->descendants()->get();
        $this->assertEquals(3, $descendants->count()); // reply1, reply2, and nested reply
    }

    public function test_materialized_path_is_correctly_set(): void
    {
        $root = Comment::factory()->create([
            'post_id' => $this->post->id,
        ]);

        $reply = Comment::factory()->reply($root)->create();
        $nestedReply = Comment::factory()->reply($reply)->create();

        $this->assertEquals((string) $root->id, $root->path);
        $this->assertStringStartsWith($root->id . '.', $reply->path);
        $this->assertStringStartsWith($reply->path . '.', $nestedReply->path);
    }

    public function test_can_get_comment_ancestors(): void
    {
        $root = Comment::factory()->create(['post_id' => $this->post->id]);
        $reply = Comment::factory()->reply($root)->create();
        $nestedReply = Comment::factory()->reply($reply)->create();

        $ancestors = $nestedReply->ancestors();
        
        $this->assertEquals(2, $ancestors->count());
        $this->assertTrue($ancestors->contains('id', $root->id));
        $this->assertTrue($ancestors->contains('id', $reply->id));
    }

    public function test_can_get_root_comment(): void
    {
        $root = Comment::factory()->create(['post_id' => $this->post->id]);
        $reply = Comment::factory()->reply($root)->create();
        $nestedReply = Comment::factory()->reply($reply)->create();

        $this->assertEquals($root->id, $root->root()->id);
        $this->assertEquals($root->id, $reply->root()->id);
        $this->assertEquals($root->id, $nestedReply->root()->id);
    }

    public function test_can_get_siblings(): void
    {
        $root = Comment::factory()->create(['post_id' => $this->post->id]);
        $reply1 = Comment::factory()->reply($root)->create();
        $reply2 = Comment::factory()->reply($root)->create();
        $reply3 = Comment::factory()->reply($root)->create();

        $siblings = $reply1->siblings()->get();
        
        $this->assertEquals(2, $siblings->count());
        $this->assertTrue($siblings->contains('id', $reply2->id));
        $this->assertTrue($siblings->contains('id', $reply3->id));
        $this->assertFalse($siblings->contains('id', $reply1->id));
    }

    public function test_comment_tree_building(): void
    {
        $root1 = Comment::factory()->create(['post_id' => $this->post->id]);
        $root2 = Comment::factory()->create(['post_id' => $this->post->id]);
        
        $reply1 = Comment::factory()->reply($root1)->create();
        $reply2 = Comment::factory()->reply($root1)->create();
        $nestedReply = Comment::factory()->reply($reply1)->create();

        $allComments = collect([$root1, $root2, $reply1, $reply2, $nestedReply]);
        $tree = Comment::buildTree($allComments);

        $this->assertEquals(2, $tree->count()); // Two root comments
        
        $firstRoot = $tree->first();
        $this->assertEquals(2, $firstRoot->replies->count());
        $this->assertEquals(1, $firstRoot->replies->first()->replies->count());
    }

    public function test_comment_scopes_work_correctly(): void
    {
        $root = Comment::factory()->create(['post_id' => $this->post->id]);
        $reply = Comment::factory()->reply($root)->create();
        $hiddenComment = Comment::factory()->hidden()->create(['post_id' => $this->post->id]);

        // Test root scope
        $rootComments = Comment::roots()->get();
        $this->assertEquals(2, $rootComments->count()); // root and hiddenComment

        // Test replies scope
        $replies = Comment::where('depth', '>', 0)->get();
        $this->assertEquals(1, $replies->count());

        // Test visible scope
        $visibleComments = Comment::visible()->get();
        $this->assertEquals(2, $visibleComments->count()); // Excludes hidden comment

        // Test depth scope
        $depthZero = Comment::byDepth(0)->get();
        $this->assertEquals(2, $depthZero->count());

        $depthOne = Comment::byDepth(1)->get();
        $this->assertEquals(1, $depthOne->count());
    }

    public function test_can_reply_validation(): void
    {
        $root = Comment::factory()->create([
            'post_id' => $this->post->id,
            'depth' => 0,
        ]);

        // Create a comment at max depth by building a proper chain
        $current = Comment::factory()->create(['post_id' => $this->post->id]);
        for ($i = 1; $i <= Comment::MAX_DEPTH; $i++) {
            $current = Comment::factory()->create([
                'post_id' => $this->post->id,
                'parent_id' => $current->id,
            ]);
        }
        $maxDepthComment = $current;
        $maxDepthComment->load('post');

        $hiddenComment = Comment::factory()->hidden()->create([
            'post_id' => $this->post->id,
        ]);

        $this->assertTrue($root->canReply());
        $this->assertFalse($maxDepthComment->canReply());
        $this->assertFalse($hiddenComment->canReply());
    }

    public function test_comment_permissions(): void
    {
        $comment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'created_at' => now()->subMinutes(5), // Within edit window
        ]);

        $oldComment = Comment::factory()->create([
            'user_id' => $this->user->id,
            'post_id' => $this->post->id,
            'created_at' => now()->subHours(1), // Outside edit window
        ]);

        // Test edit permissions
        $this->assertTrue($comment->canEditBy($this->user));
        $this->assertFalse($oldComment->canEditBy($this->user));
        // Load the post relationship first
        $comment->load('post');
        // This should be true because otherUser is the post owner
        $this->assertTrue($comment->canEditBy($this->otherUser));

        // Test delete permissions
        $this->assertTrue($comment->canDeleteBy($this->user));
        $this->assertTrue($oldComment->canDeleteBy($this->user));
        // Load the post relationship first
        $comment->load('post');
        // This should be true because otherUser is the post owner
        $this->assertTrue($comment->canDeleteBy($this->otherUser));

        // Post owner should be able to edit/delete comments on their post
        // Load the post relationship for proper permissions checking
        $comment->load('post');
        $this->assertTrue($comment->canEditBy($this->otherUser)); // Post owner
        $this->assertTrue($comment->canDeleteBy($this->otherUser)); // Post owner
    }

    public function test_comment_accessors(): void
    {
        $root = Comment::factory()->create(['post_id' => $this->post->id]);
        $reply = Comment::factory()->reply($root)->withReplies(2)->create();

        $this->assertTrue($root->is_root);
        $this->assertFalse($reply->is_root);
        
        $reply->refresh();
        $this->assertTrue($reply->has_replies);
        $this->assertFalse($root->has_replies);
        
        $this->assertTrue($root->can_reply);
        $this->assertTrue($reply->can_reply);
    }

    public function test_like_count_management(): void
    {
        $comment = Comment::factory()->create(['post_id' => $this->post->id]);

        $this->assertEquals(0, $comment->likes_count);

        $comment->incrementLikes();
        $this->assertEquals(1, $comment->fresh()->likes_count);

        $comment->incrementLikes();
        $this->assertEquals(2, $comment->fresh()->likes_count);

        $comment->decrementLikes();
        $this->assertEquals(1, $comment->fresh()->likes_count);

        $comment->decrementLikes();
        $this->assertEquals(0, $comment->fresh()->likes_count);

        // Should not go below 0
        $comment->decrementLikes();
        $this->assertEquals(0, $comment->fresh()->likes_count);
    }

    public function test_post_comment_count_updates_automatically(): void
    {
        $initialCount = $this->post->comments_count;

        $comment1 = Comment::factory()->create(['post_id' => $this->post->id]);
        $this->post->refresh();
        $this->assertEquals($initialCount + 1, $this->post->comments_count);

        $reply = Comment::factory()->reply($comment1)->create();
        $this->post->refresh();
        $this->assertEquals($initialCount + 2, $this->post->comments_count);

        $reply->delete();
        $this->post->refresh();
        $this->assertEquals($initialCount + 1, $this->post->comments_count);
    }

    public function test_comment_factory_methods(): void
    {
        // Test text comment
        $textComment = Comment::factory()->text()->create(['post_id' => $this->post->id]);
        $this->assertEquals('text', $textComment->type);

        // Test image comment
        $imageComment = Comment::factory()->image()->create(['post_id' => $this->post->id]);
        $this->assertEquals('image', $imageComment->type);

        // Test gif comment
        $gifComment = Comment::factory()->gif()->create(['post_id' => $this->post->id]);
        $this->assertEquals('gif', $gifComment->type);

        // Test popular comment
        $popularComment = Comment::factory()->popular()->create(['post_id' => $this->post->id]);
        $this->assertGreaterThan(0, $popularComment->likes_count);

        // Test hidden comment
        $hiddenComment = Comment::factory()->hidden()->create(['post_id' => $this->post->id]);
        $this->assertTrue($hiddenComment->is_hidden);

        // Test reported comment
        $reportedComment = Comment::factory()->reported()->create(['post_id' => $this->post->id]);
        $this->assertTrue($reportedComment->is_reported);
    }
} 