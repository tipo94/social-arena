<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Follow;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FollowSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private User $user3;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->user3 = User::factory()->create();

        // Ensure profiles exist
        $this->user1->profile()->firstOrCreate([]);
        $this->user2->profile()->firstOrCreate([]);
        $this->user3->profile()->firstOrCreate([]);
    }

    public function test_can_follow_user(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/follow/user', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'id',
                        'followed_at',
                        'is_muted',
                        'show_notifications',
                        'is_close_friend',
                    ],
                ]);

        $this->assertDatabaseHas('follows', [
            'follower_id' => $this->user1->id,
            'following_id' => $this->user2->id,
        ]);

        $this->assertTrue($this->user1->isFollowing($this->user2));
        $this->assertTrue($this->user2->isFollowedBy($this->user1));
    }

    public function test_can_unfollow_user(): void
    {
        // First follow
        $follow = Follow::factory()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->deleteJson('/api/follow/user', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('follows', [
            'follower_id' => $this->user1->id,
            'following_id' => $this->user2->id,
        ]);

        $this->assertFalse($this->user1->isFollowing($this->user2));
        $this->assertFalse($this->user2->isFollowedBy($this->user1));
    }

    public function test_can_toggle_follow_status(): void
    {
        Sanctum::actingAs($this->user1);

        // First toggle (follow)
        $response = $this->postJson('/api/follow/toggle', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_following' => true,
                        'action' => 'followed',
                    ],
                ]);

        $this->assertTrue($this->user1->isFollowing($this->user2));

        // Second toggle (unfollow)
        $response = $this->postJson('/api/follow/toggle', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'is_following' => false,
                        'action' => 'unfollowed',
                    ],
                ]);

        $this->assertFalse($this->user1->isFollowing($this->user2));
    }

    public function test_cannot_follow_self(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/follow/user', [
            'user_id' => $this->user1->id,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'You cannot follow yourself',
                ]);
    }

    public function test_cannot_follow_same_user_twice(): void
    {
        Follow::factory()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/follow/user', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'You are already following this user',
                ]);
    }

    public function test_cannot_unfollow_if_not_following(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->deleteJson('/api/follow/user', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'You are not following this user',
                ]);
    }

    public function test_can_get_following_list(): void
    {
        Follow::factory()->between($this->user1, $this->user2)->create();
        Follow::factory()->between($this->user1, $this->user3)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/follow/following');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'followed_at',
                            'is_muted',
                            'show_notifications',
                            'is_close_friend',
                            'following' => [
                                'id',
                                'name',
                                'username',
                            ],
                        ],
                    ],
                    'pagination',
                ]);

        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_get_followers_list(): void
    {
        Follow::factory()->between($this->user2, $this->user1)->create();
        Follow::factory()->between($this->user3, $this->user1)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/follow/followers');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        '*' => [
                            'id',
                            'followed_at',
                            'follower' => [
                                'id',
                                'name',
                                'username',
                            ],
                        ],
                    ],
                    'pagination',
                ]);

        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_search_following_list(): void
    {
        $this->user2->update(['name' => 'Alice Smith']);
        $this->user3->update(['name' => 'Bob Jones']);
        
        Follow::factory()->between($this->user1, $this->user2)->create();
        Follow::factory()->between($this->user1, $this->user3)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/follow/following?search=Alice');

        $response->assertStatus(200);
        $data = $response->json('data');
        
        $this->assertEquals(1, count($data));
        $this->assertEquals($this->user2->id, $data[0]['following']['id']);
    }

    public function test_can_filter_muted_follows(): void
    {
        Follow::factory()->between($this->user1, $this->user2)->active()->create();
        Follow::factory()->between($this->user1, $this->user3)->muted()->create();
        
        Sanctum::actingAs($this->user1);

        // Get only active follows (default)
        $response = $this->getJson('/api/follow/following');
        $this->assertEquals(1, count($response->json('data')));

        // Get all follows including muted
        $response = $this->getJson('/api/follow/following?include_muted=true');
        $this->assertEquals(2, count($response->json('data')));
    }

    public function test_can_get_follow_statistics(): void
    {
        // User1 follows user2 and user3
        Follow::factory()->between($this->user1, $this->user2)->create();
        Follow::factory()->between($this->user1, $this->user3)->closeFriend()->create();
        
        // User2 follows user1
        Follow::factory()->between($this->user2, $this->user1)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/follow/statistics');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'total_following',
                        'total_followers',
                        'active_following',
                        'muted_following',
                        'close_friends',
                        'recent_follows',
                        'recent_followers',
                    ],
                ]);

        $stats = $response->json('data');
        $this->assertEquals(2, $stats['total_following']);
        $this->assertEquals(1, $stats['total_followers']);
        $this->assertEquals(1, $stats['close_friends']);
    }

    public function test_can_update_follow_settings(): void
    {
        $follow = Follow::factory()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->patchJson("/api/follow/{$follow->id}/settings", [
            'is_muted' => true,
            'is_close_friend' => true,
        ]);

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $follow->refresh();
        $this->assertTrue($follow->is_muted);
        $this->assertTrue($follow->is_close_friend);
    }

    public function test_cannot_update_others_follow_settings(): void
    {
        $follow = Follow::factory()->between($this->user2, $this->user3)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->patchJson("/api/follow/{$follow->id}/settings", [
            'is_muted' => true,
        ]);

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'You cannot modify this follow relationship',
                ]);
    }

    public function test_follow_model_relationships(): void
    {
        $follow = Follow::factory()->between($this->user1, $this->user2)->create();

        $this->assertEquals($this->user1->id, $follow->follower->id);
        $this->assertEquals($this->user2->id, $follow->following->id);
    }

    public function test_follow_model_scopes(): void
    {
        Follow::factory()->between($this->user1, $this->user2)->active()->create();
        Follow::factory()->between($this->user1, $this->user3)->muted()->create();
        Follow::factory()->between($this->user2, $this->user3)->closeFriend()->create();

        $this->assertEquals(1, Follow::active()->count());
        $this->assertEquals(1, Follow::muted()->count());
        $this->assertEquals(1, Follow::closeFriends()->count());
        $this->assertEquals(2, Follow::byFollower($this->user1->id)->count());
        $this->assertEquals(1, Follow::ofUser($this->user2->id)->count());
    }

    public function test_follow_updates_profile_counts(): void
    {
        $this->assertEquals(0, $this->user1->profile->fresh()->following_count);
        $this->assertEquals(0, $this->user2->profile->fresh()->followers_count);

        // Follow user2
        $this->user1->follow($this->user2);

        $this->assertEquals(1, $this->user1->profile->fresh()->following_count);
        $this->assertEquals(1, $this->user2->profile->fresh()->followers_count);

        // Unfollow user2
        $this->user1->unfollow($this->user2);

        $this->assertEquals(0, $this->user1->profile->fresh()->following_count);
        $this->assertEquals(0, $this->user2->profile->fresh()->followers_count);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/follow/user', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_user_id(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/follow/user', [
            'user_id' => 'invalid',
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);

        $response = $this->postJson('/api/follow/user', [
            'user_id' => 99999,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_follow_asymmetric_relationship(): void
    {
        // User1 follows User2 (one-way)
        $this->user1->follow($this->user2);

        $this->assertTrue($this->user1->isFollowing($this->user2));
        $this->assertFalse($this->user2->isFollowing($this->user1));
        
        $this->assertTrue($this->user2->isFollowedBy($this->user1));
        $this->assertFalse($this->user1->isFollowedBy($this->user2));
    }

    public function test_follow_is_different_from_friendship(): void
    {
        // Following someone doesn't make them friends
        $this->user1->follow($this->user2);

        $this->assertTrue($this->user1->isFollowing($this->user2));
        $this->assertFalse($this->user1->isFriendsWith($this->user2));
    }
} 