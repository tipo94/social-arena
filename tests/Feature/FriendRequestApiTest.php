<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class FriendRequestApiTest extends TestCase
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
    }

    public function test_can_send_friend_request(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Friend request sent successfully',
                ])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'status',
                        'is_pending',
                        'user',
                        'friend',
                        'requested_at',
                    ],
                ]);

        $this->assertDatabaseHas('friendships', [
            'user_id' => $this->user1->id,
            'friend_id' => $this->user2->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_send_friend_request_to_self(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user1->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_send_friend_request_to_non_existent_user(): void
    {
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => 99999,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_send_duplicate_friend_request(): void
    {
        Sanctum::actingAs($this->user1);

        // Send first request
        $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ])->assertStatus(201);

        // Try to send again
        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_send_friend_request_if_already_friends(): void
    {
        Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_send_friend_request_if_blocked(): void
    {
        Friendship::factory()->blocked()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_cannot_send_friend_request_if_user_disabled_requests(): void
    {
        $this->user2->profile->update(['allow_friend_requests' => false]);
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }

    public function test_can_accept_friend_request(): void
    {
        $friendship = Friendship::factory()->pending()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user2); // Recipient accepts

        $response = $this->postJson("/api/friends/{$friendship->id}/accept");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Friend request accepted successfully',
                ])
                ->assertJsonPath('data.status', 'accepted')
                ->assertJsonPath('data.is_accepted', true);

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'accepted',
        ]);

        $this->assertDatabaseMissing('friendships', [
            'id' => $friendship->id,
            'accepted_at' => null,
        ]);
    }

    public function test_can_decline_friend_request(): void
    {
        $friendship = Friendship::factory()->pending()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user2); // Recipient declines

        $response = $this->postJson("/api/friends/{$friendship->id}/decline");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Friend request declined successfully',
                ]);

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'declined',
        ]);
    }

    public function test_sender_cannot_accept_own_request(): void
    {
        $friendship = Friendship::factory()->pending()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1); // Sender tries to accept

        $response = $this->postJson("/api/friends/{$friendship->id}/accept");

        $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'You cannot accept this friend request',
                ]);
    }

    public function test_cannot_accept_non_pending_request(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user2);

        $response = $this->postJson("/api/friends/{$friendship->id}/accept");

        $response->assertStatus(422)
                ->assertJson([
                    'success' => false,
                    'message' => 'This friend request cannot be accepted',
                ]);
    }

    public function test_can_block_user(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson("/api/friends/{$friendship->id}/block");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User blocked successfully',
                ]);

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'blocked',
        ]);
    }

    public function test_can_unblock_user(): void
    {
        $friendship = Friendship::factory()->blocked()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson("/api/friends/{$friendship->id}/unblock");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'User unblocked successfully',
                ]);

        $this->assertDatabaseHas('friendships', [
            'id' => $friendship->id,
            'status' => 'declined',
        ]);
    }

    public function test_can_remove_friendship(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->deleteJson("/api/friends/{$friendship->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Friendship removed successfully',
                ]);

        $this->assertSoftDeleted('friendships', [
            'id' => $friendship->id,
        ]);
    }

    public function test_can_get_friends_list(): void
    {
        // Create some friendships
        $friend1 = Friendship::factory()->accepted()->receivedBy($this->user1)->create();
        $friend2 = Friendship::factory()->accepted()->sentBy($this->user1)->create();
        $pending = Friendship::factory()->pending()->receivedBy($this->user1)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/friends?status=accepted');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'friendships' => [
                            '*' => [
                                'id',
                                'status',
                                'is_accepted',
                                'user',
                                'friend',
                                'other_user',
                            ],
                        ],
                        'pagination' => [
                            'current_page',
                            'total',
                            'per_page',
                        ],
                    ],
                ]);

        $this->assertEquals(2, count($response->json('data.friendships')));
    }

    public function test_can_get_pending_requests(): void
    {
        $pending1 = Friendship::factory()->pending()->receivedBy($this->user1)->create();
        $pending2 = Friendship::factory()->pending()->sentBy($this->user1)->create();
        
        Sanctum::actingAs($this->user1);

        // Get received pending requests
        $response = $this->getJson('/api/friends?status=pending&type=received');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $this->assertEquals(1, count($response->json('data.friendships')));

        // Get sent pending requests
        $response = $this->getJson('/api/friends?status=pending&type=sent');

        $response->assertStatus(200);
        $this->assertEquals(1, count($response->json('data.friendships')));
    }

    public function test_can_search_friends(): void
    {
        $friend = User::factory()->create(['name' => 'John Doe']);
        Friendship::factory()->accepted()->between($this->user1, $friend)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/friends?search=John');

        $response->assertStatus(200)
                ->assertJson(['success' => true]);

        $friendships = $response->json('data.friendships');
        $this->assertCount(1, $friendships);
    }

    public function test_can_get_mutual_friends(): void
    {
        // Create mutual friend scenario
        $mutualFriend = User::factory()->create();
        
        Friendship::factory()->accepted()->between($this->user1, $mutualFriend)->create();
        Friendship::factory()->accepted()->between($this->user2, $mutualFriend)->create();
        Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson("/api/friends/mutual/{$this->user2->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'count' => 1,
                    ],
                ])
                ->assertJsonStructure([
                    'data' => [
                        'mutual_friends',
                        'count',
                        'friendship_duration',
                    ],
                ]);
    }

    public function test_can_get_friendship_statistics(): void
    {
        // Create various friendships
        Friendship::factory()->accepted()->sentBy($this->user1)->create();
        Friendship::factory()->pending()->receivedBy($this->user1)->create();
        Friendship::factory()->blocked()->sentBy($this->user1)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/friends/statistics');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'statistics' => [
                            'total_friends',
                            'pending_sent',
                            'pending_received',
                            'blocked_users',
                            'declined_requests',
                            'mutual_friends_avg',
                            'recent_friendships',
                        ],
                        'generated_at',
                    ],
                ]);
    }

    public function test_can_get_friend_suggestions(): void
    {
        // Create some users that could be suggested
        User::factory()->count(5)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson('/api/friends/suggestions?count=3');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'suggestions',
                        'algorithm',
                        'count',
                        'generated_at',
                    ],
                ]);
    }

    public function test_can_get_specific_friendship(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson("/api/friends/{$friendship->id}");

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'status',
                        'user',
                        'friend',
                        'other_user',
                        'permissions',
                        'can_accept',
                        'can_decline',
                        'can_block',
                        'can_remove',
                    ],
                ]);
    }

    public function test_cannot_view_other_users_friendships(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user2, $this->user3)->create([
            'show_in_friends_list' => false, // Make it hidden
        ]);
        
        Sanctum::actingAs($this->user1);

        $response = $this->getJson("/api/friends/{$friendship->id}");

        $response->assertStatus(404)
                ->assertJson([
                    'success' => false,
                    'message' => 'Friendship not found or not accessible',
                ]);
    }

    public function test_unauthenticated_user_cannot_access_friend_endpoints(): void
    {
        $friendship = Friendship::factory()->create();

        // Test various endpoints without authentication
        $this->getJson('/api/friends')->assertStatus(401);
        $this->postJson('/api/friends/request')->assertStatus(401);
        $this->getJson("/api/friends/{$friendship->id}")->assertStatus(401);
        $this->postJson("/api/friends/{$friendship->id}/accept")->assertStatus(401);
        $this->postJson("/api/friends/{$friendship->id}/decline")->assertStatus(401);
        $this->deleteJson("/api/friends/{$friendship->id}")->assertStatus(401);
    }

    public function test_friend_request_respects_privacy_settings(): void
    {
        // Set target user to only accept requests from friends of friends
        $this->user2->profile->update(['friend_request_visibility' => 'friends_of_friends']);
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);

        // Create mutual friend and try again
        $mutualFriend = User::factory()->create();
        Friendship::factory()->accepted()->between($this->user1, $mutualFriend)->create();
        Friendship::factory()->accepted()->between($this->user2, $mutualFriend)->create();

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(201);
    }

    public function test_cannot_resend_declined_request_too_soon(): void
    {
        $friendship = Friendship::factory()->declined()->between($this->user1, $this->user2)->create();
        $friendship->update(['updated_at' => now()->subDays(10)]); // Recent decline
        
        Sanctum::actingAs($this->user1);

        $response = $this->postJson('/api/friends/request', [
            'user_id' => $this->user2->id,
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id']);
    }
} 