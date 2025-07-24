<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Post;
use App\Models\Comment;
use App\Models\Notification;
use App\Models\Follow;
use App\Models\Friendship;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected NotificationService $notificationService;
    protected User $user;
    protected User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->notificationService = app(NotificationService::class);
        $this->user = User::factory()->create();
        $this->actor = User::factory()->create();
    }

    /** @test */
    public function can_create_like_notification_for_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);
        
        $notification = $this->notificationService->createLikeNotification($this->actor, $post);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals($this->actor->id, $notification->actor_id);
        $this->assertEquals(Notification::TYPE_LIKE, $notification->type);
        $this->assertEquals('Your post was liked', $notification->title);
        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'actor_id' => $this->actor->id,
            'type' => Notification::TYPE_LIKE,
        ]);
    }

    /** @test */
    public function does_not_create_like_notification_for_own_post(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);
        
        $notification = $this->notificationService->createLikeNotification($this->user, $post);

        $this->assertNull($notification);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $this->user->id,
            'actor_id' => $this->user->id,
            'type' => Notification::TYPE_LIKE,
        ]);
    }

    /** @test */
    public function can_create_comment_notification(): void
    {
        $post = Post::factory()->create(['user_id' => $this->user->id]);
        $comment = Comment::factory()->create([
            'user_id' => $this->actor->id,
            'post_id' => $post->id,
        ]);
        
        $notification = $this->notificationService->createCommentNotification($this->actor, $comment);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals($this->actor->id, $notification->actor_id);
        $this->assertEquals(Notification::TYPE_COMMENT, $notification->type);
        $this->assertEquals('New comment on your post', $notification->title);
    }

    /** @test */
    public function can_create_follow_notification(): void
    {
        $notification = $this->notificationService->createFollowNotification($this->actor, $this->user);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals($this->actor->id, $notification->actor_id);
        $this->assertEquals(Notification::TYPE_FOLLOW, $notification->type);
        $this->assertEquals('New follower', $notification->title);
    }

    /** @test */
    public function can_create_friend_request_notification(): void
    {
        $notification = $this->notificationService->createFriendRequestNotification($this->actor, $this->user);

        $this->assertInstanceOf(Notification::class, $notification);
        $this->assertEquals($this->user->id, $notification->user_id);
        $this->assertEquals($this->actor->id, $notification->actor_id);
        $this->assertEquals(Notification::TYPE_FRIEND_REQUEST, $notification->type);
        $this->assertEquals(Notification::PRIORITY_HIGH, $notification->priority);
    }

    /** @test */
    public function can_get_user_notifications_via_api(): void
    {
        // Create some notifications
        Notification::factory()->count(5)->create(['user_id' => $this->user->id]);
        Notification::factory()->count(3)->unread()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications');

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'id',
                             'type',
                             'title',
                             'message',
                             'is_read',
                             'is_unread',
                             'created_at',
                             'time_ago',
                         ]
                     ],
                     'meta'
                 ]);
    }

    /** @test */
    public function can_get_unread_notifications_count(): void
    {
        Notification::factory()->count(5)->read()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(3)->unread()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications/unread-count');

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'unread_count' => 3
                     ]
                 ]);
    }

    /** @test */
    public function can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->unread()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'is_read' => true,
                         'is_unread' => false,
                     ]
                 ]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /** @test */
    public function can_mark_all_notifications_as_read(): void
    {
        Notification::factory()->count(5)->unread()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson('/api/notifications/mark-all-read');

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'marked_count' => 5
                     ]
                 ]);

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->unread()->count());
    }

    /** @test */
    public function can_dismiss_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/notifications/{$notification->id}/dismiss");

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'is_dismissed' => true,
                     ]
                 ]);

        $this->assertTrue($notification->fresh()->is_dismissed);
    }

    /** @test */
    public function can_delete_notification(): void
    {
        $notification = Notification::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->deleteJson("/api/notifications/{$notification->id}");

        $response->assertOk()
                 ->assertJson(['success' => true]);

        $this->assertSoftDeleted('notifications', ['id' => $notification->id]);
    }

    /** @test */
    public function can_bulk_mark_notifications_as_read(): void
    {
        $notifications = Notification::factory()->count(3)->unread()->create(['user_id' => $this->user->id]);
        $notificationIds = $notifications->pluck('id')->toArray();

        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/notifications/bulk/mark-read', [
                             'notification_ids' => $notificationIds
                         ]);

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'marked_count' => 3
                     ]
                 ]);

        foreach ($notifications as $notification) {
            $this->assertNotNull($notification->fresh()->read_at);
        }
    }

    /** @test */
    public function can_get_notification_statistics(): void
    {
        // Create various notifications
        Notification::factory()->count(10)->create(['user_id' => $this->user->id]);
        Notification::factory()->count(5)->unread()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(2)->like()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(3)->comment()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications/statistics');

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         'total',
                         'unread',
                         'today',
                         'this_week',
                         'by_type',
                         'high_priority'
                     ]
                 ]);
    }

    /** @test */
    public function can_get_notification_types_with_counts(): void
    {
        Notification::factory()->count(2)->like()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(3)->comment()->unread()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(1)->follow()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications/types');

        $response->assertOk()
                 ->assertJsonStructure([
                     'success',
                     'data' => [
                         '*' => [
                             'type',
                             'total',
                             'unread'
                         ]
                     ]
                 ]);

        $types = collect($response->json('data'));
        $likeType = $types->firstWhere('type', Notification::TYPE_LIKE);
        $commentType = $types->firstWhere('type', Notification::TYPE_COMMENT);

        $this->assertEquals(2, $likeType['total']);
        $this->assertEquals(3, $commentType['total']);
        $this->assertEquals(3, $commentType['unread']);
    }

    /** @test */
    public function cannot_access_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson("/api/notifications/{$notification->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function cannot_modify_other_users_notifications(): void
    {
        $otherUser = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->patchJson("/api/notifications/{$notification->id}/read");

        $response->assertNotFound();
    }

    /** @test */
    public function notification_model_scopes_work_correctly(): void
    {
        $readNotifications = Notification::factory()->count(3)->read()->create(['user_id' => $this->user->id]);
        $unreadNotifications = Notification::factory()->count(2)->unread()->create(['user_id' => $this->user->id]);
        $dismissedNotifications = Notification::factory()->count(1)->dismissed()->create(['user_id' => $this->user->id]);

        $this->assertEquals(3, Notification::where('user_id', $this->user->id)->read()->count());
        $this->assertEquals(2, Notification::where('user_id', $this->user->id)->unread()->count());
        $this->assertEquals(1, Notification::where('user_id', $this->user->id)->dismissed()->count());
        $this->assertEquals(5, Notification::where('user_id', $this->user->id)->active()->count());
    }

    /** @test */
    public function notification_accessors_work_correctly(): void
    {
        $readNotification = Notification::factory()->read()->create();
        $unreadNotification = Notification::factory()->unread()->create();

        $this->assertTrue($readNotification->is_read);
        $this->assertFalse($readNotification->is_unread);
        $this->assertFalse($unreadNotification->is_read);
        $this->assertTrue($unreadNotification->is_unread);
        $this->assertIsString($readNotification->time_ago);
    }

    /** @test */
    public function can_test_notification_creation_via_api(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
                         ->postJson('/api/notifications/test', [
                             'type' => Notification::TYPE_LIKE,
                             'title' => 'Test notification',
                             'message' => 'This is a test message'
                         ]);

        $response->assertOk()
                 ->assertJson([
                     'success' => true,
                     'message' => 'Test notification created'
                 ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'actor_id' => $this->user->id,
            'type' => Notification::TYPE_LIKE,
            'title' => 'Test notification',
            'message' => 'This is a test message'
        ]);
    }

    /** @test */
    public function notification_filters_work_correctly(): void
    {
        Notification::factory()->count(3)->like()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(2)->comment()->create(['user_id' => $this->user->id]);
        Notification::factory()->count(2)->unread()->create(['user_id' => $this->user->id]);

        // Test type filter
        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications?type=' . Notification::TYPE_LIKE);

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));

        // Test unread filter
        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson('/api/notifications?unread_only=true');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function requires_authentication_for_notification_endpoints(): void
    {
        $response = $this->getJson('/api/notifications');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/notifications/unread-count');
        $response->assertUnauthorized();

        $response = $this->getJson('/api/notifications/statistics');
        $response->assertUnauthorized();
    }
} 