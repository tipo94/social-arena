<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FriendshipModelTest extends TestCase
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

    public function test_can_create_friendship(): void
    {
        $friendship = Friendship::factory()->between($this->user1, $this->user2)->create();

        $this->assertDatabaseHas('friendships', [
            'user_id' => $this->user1->id,
            'friend_id' => $this->user2->id,
        ]);

        $this->assertEquals($this->user1->id, $friendship->user_id);
        $this->assertEquals($this->user2->id, $friendship->friend_id);
    }

    public function test_friendship_relationships_work(): void
    {
        $friendship = Friendship::factory()->between($this->user1, $this->user2)->create();

        $this->assertEquals($this->user1->id, $friendship->user->id);
        $this->assertEquals($this->user2->id, $friendship->friend->id);
    }

    public function test_friendship_status_constants(): void
    {
        $this->assertEquals('pending', Friendship::STATUS_PENDING);
        $this->assertEquals('accepted', Friendship::STATUS_ACCEPTED);
        $this->assertEquals('blocked', Friendship::STATUS_BLOCKED);
        $this->assertEquals('declined', Friendship::STATUS_DECLINED);
    }

    public function test_friendship_accessors(): void
    {
        $pendingFriendship = Friendship::factory()->pending()->create();
        $acceptedFriendship = Friendship::factory()->accepted()->create();
        $blockedFriendship = Friendship::factory()->blocked()->create();

        $this->assertTrue($pendingFriendship->is_pending);
        $this->assertFalse($pendingFriendship->is_accepted);
        $this->assertFalse($pendingFriendship->is_blocked);

        $this->assertFalse($acceptedFriendship->is_pending);
        $this->assertTrue($acceptedFriendship->is_accepted);
        $this->assertFalse($acceptedFriendship->is_blocked);

        $this->assertFalse($blockedFriendship->is_pending);
        $this->assertFalse($blockedFriendship->is_accepted);
        $this->assertTrue($blockedFriendship->is_blocked);
    }

    public function test_can_accept_pending_friendship(): void
    {
        $friendship = Friendship::factory()->pending()->between($this->user1, $this->user2)->create();

        $result = $friendship->accept();

        $this->assertTrue($result);
        $this->assertEquals(Friendship::STATUS_ACCEPTED, $friendship->fresh()->status);
        $this->assertNotNull($friendship->fresh()->accepted_at);
    }

    public function test_cannot_accept_non_pending_friendship(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        $result = $friendship->accept();

        $this->assertFalse($result);
    }

    public function test_can_decline_pending_friendship(): void
    {
        $friendship = Friendship::factory()->pending()->create();

        $result = $friendship->decline();

        $this->assertTrue($result);
        $this->assertEquals(Friendship::STATUS_DECLINED, $friendship->fresh()->status);
    }

    public function test_cannot_decline_non_pending_friendship(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        $result = $friendship->decline();

        $this->assertFalse($result);
    }

    public function test_can_block_user(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        $result = $friendship->block();

        $this->assertTrue($result);
        $this->assertEquals(Friendship::STATUS_BLOCKED, $friendship->fresh()->status);
        $this->assertNotNull($friendship->fresh()->blocked_at);
    }

    public function test_can_unblock_user(): void
    {
        $friendship = Friendship::factory()->blocked()->create();

        $result = $friendship->unblock();

        $this->assertTrue($result);
        $this->assertEquals(Friendship::STATUS_DECLINED, $friendship->fresh()->status);
        $this->assertNull($friendship->fresh()->blocked_at);
    }

    public function test_cannot_unblock_non_blocked_user(): void
    {
        $friendship = Friendship::factory()->accepted()->create();

        $result = $friendship->unblock();

        $this->assertFalse($result);
    }

    public function test_can_be_modified_by_correct_users(): void
    {
        $friendship = Friendship::factory()->pending()->between($this->user1, $this->user2)->create();

        // Recipient can modify pending request
        $this->assertTrue($friendship->canBeModifiedBy($this->user2));
        
        // Sender cannot modify pending request
        $this->assertFalse($friendship->canBeModifiedBy($this->user1));

                 // Accept the friendship
         $friendship->accept();
         $friendship = $friendship->fresh(); // Reload to get updated status

         // Both users can modify accepted friendship
         $this->assertTrue($friendship->canBeModifiedBy($this->user1));
         $this->assertTrue($friendship->canBeModifiedBy($this->user2));

        // Other users cannot modify
        $this->assertFalse($friendship->canBeModifiedBy($this->user3));
    }

    public function test_visibility_to_users(): void
    {
        $friendship = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create([
            'show_in_friends_list' => true,
        ]);

        // Users involved can see it
        $this->assertTrue($friendship->isVisibleTo($this->user1));
        $this->assertTrue($friendship->isVisibleTo($this->user2));

        // Other users can see accepted friendships
        $this->assertTrue($friendship->isVisibleTo($this->user3));

        // Hidden friendship
        $friendship->update(['show_in_friends_list' => false]);
        $this->assertFalse($friendship->isVisibleTo($this->user3));
        
        // But still visible to involved users
        $this->assertTrue($friendship->isVisibleTo($this->user1));
        $this->assertTrue($friendship->isVisibleTo($this->user2));
    }

    public function test_get_other_user(): void
    {
        $friendship = Friendship::factory()->between($this->user1, $this->user2)->create();

        $otherUser1 = $friendship->getOtherUser($this->user1);
        $otherUser2 = $friendship->getOtherUser($this->user2);

        $this->assertEquals($this->user2->id, $otherUser1->id);
        $this->assertEquals($this->user1->id, $otherUser2->id);

        // User not involved in friendship
        $otherUser3 = $friendship->getOtherUser($this->user3);
        $this->assertNull($otherUser3);
    }

    public function test_friendship_duration_calculation(): void
    {
        $acceptedAt = now()->subDays(30);
        $friendship = Friendship::factory()->accepted()->create([
            'accepted_at' => $acceptedAt,
        ]);

        $this->assertEquals(30, $friendship->friendship_duration);

        // Pending friendship has no duration
        $pendingFriendship = Friendship::factory()->pending()->create();
        $this->assertNull($pendingFriendship->friendship_duration);
    }

    public function test_mutual_friends_calculation(): void
    {
        // Create a mutual friend scenario
        $mutualFriend = User::factory()->create();

        // User1 and mutual friend are friends
        $friendship1 = Friendship::factory()->accepted()->between($this->user1, $mutualFriend)->create();
        
        // User2 and mutual friend are friends
        $friendship2 = Friendship::factory()->accepted()->between($this->user2, $mutualFriend)->create();

        // User1 and User2 become friends
        $friendship3 = Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();

        // Calculate mutual friends
        $mutualCount = $friendship3->calculateMutualFriendsCount();
        $this->assertEquals(1, $mutualCount);
    }

    public function test_query_scopes_work(): void
    {
        // Create friendships with different statuses
        $pending = Friendship::factory()->pending()->create();
        $accepted = Friendship::factory()->accepted()->create();
        $blocked = Friendship::factory()->blocked()->create();
        $declined = Friendship::factory()->declined()->create();

        // Test status scopes
        $this->assertEquals(1, Friendship::pending()->count());
        $this->assertEquals(1, Friendship::accepted()->count());
        $this->assertEquals(1, Friendship::blocked()->count());
        $this->assertEquals(1, Friendship::declined()->count());

        // Test user-specific scopes
        $userFriendships = Friendship::factory()->sentBy($this->user1)->count(3)->create();
        $this->assertEquals(3, Friendship::sentBy($this->user1->id)->count());

        $receivedFriendships = Friendship::factory()->receivedBy($this->user1)->count(2)->create();
        $this->assertEquals(2, Friendship::receivedBy($this->user1->id)->count());

        $involvingUser = Friendship::involvingUser($this->user1->id)->count();
        $this->assertEquals(5, $involvingUser); // 3 sent + 2 received
    }

    public function test_between_users_scope(): void
    {
        $friendship = Friendship::factory()->between($this->user1, $this->user2)->create();

        $found1 = Friendship::betweenUsers($this->user1->id, $this->user2->id)->first();
        $found2 = Friendship::betweenUsers($this->user2->id, $this->user1->id)->first();

        $this->assertEquals($friendship->id, $found1->id);
        $this->assertEquals($friendship->id, $found2->id);
    }

    public function test_recent_and_old_scopes(): void
    {
        // Create a recent accepted friendship
        $recentFriendship = Friendship::factory()->create([
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now()->subDays(3),
            'requested_at' => now()->subDays(4),
        ]);

        // Create an old accepted friendship
        $oldFriendship = Friendship::factory()->create([
            'status' => Friendship::STATUS_ACCEPTED,
            'accepted_at' => now()->subDays(400), // Older than 365 days
            'requested_at' => now()->subDays(401),
        ]);

        $this->assertEquals(1, Friendship::recent()->count());
        $this->assertEquals(1, Friendship::old()->count());
    }

    public function test_interaction_permission_scopes(): void
    {
        $allowAll = Friendship::factory()->allowAllInteractions()->create();
        $restrictedAll = Friendship::factory()->restrictedInteractions()->create();

        $this->assertEquals(1, Friendship::canSeeEachOthersPosts()->count());
        $this->assertEquals(1, Friendship::canMessage()->count());
        $this->assertEquals(1, Friendship::visible()->count());
    }

    public function test_friendship_statistics(): void
    {
        // Create various friendships for user1
        Friendship::factory()->accepted()->sentBy($this->user1)->create();
        Friendship::factory()->accepted()->receivedBy($this->user1)->create();
        Friendship::factory()->pending()->sentBy($this->user1)->create();
        Friendship::factory()->pending()->receivedBy($this->user1)->create();
        Friendship::factory()->blocked()->sentBy($this->user1)->create();
        Friendship::factory()->declined()->receivedBy($this->user1)->create();

        $stats = Friendship::getStatsForUser($this->user1);

        $this->assertEquals(2, $stats['total_friends']);
        $this->assertEquals(1, $stats['pending_sent']);
        $this->assertEquals(1, $stats['pending_received']);
        $this->assertEquals(1, $stats['blocked_users']);
        $this->assertEquals(1, $stats['declined_requests']);
        $this->assertIsInt($stats['mutual_friends_avg']);
        $this->assertIsInt($stats['recent_friendships']);
    }

    public function test_find_or_create_between_users(): void
    {
        // First call creates new friendship
        $friendship1 = Friendship::findOrCreateBetween($this->user1, $this->user2);
        $this->assertInstanceOf(Friendship::class, $friendship1);
        $this->assertEquals(Friendship::STATUS_PENDING, $friendship1->status);

        // Second call finds existing friendship
        $friendship2 = Friendship::findOrCreateBetween($this->user1, $this->user2);
        $this->assertEquals($friendship1->id, $friendship2->id);

        // Cannot create friendship with self
        $selfFriendship = Friendship::findOrCreateBetween($this->user1, $this->user1);
        $this->assertNull($selfFriendship);
    }

    public function test_can_be_friends_validation(): void
    {
        // Normal case - can be friends
        $this->assertTrue(Friendship::canBeFriends($this->user1, $this->user2));

        // Cannot be friends with self
        $this->assertFalse(Friendship::canBeFriends($this->user1, $this->user1));

        // Cannot be friends if already friends
        Friendship::factory()->accepted()->between($this->user1, $this->user2)->create();
        $this->assertFalse(Friendship::canBeFriends($this->user1, $this->user2));

        // Cannot be friends if blocked
        $this->user3->profile->update(['allow_friend_requests' => true]);
        Friendship::factory()->blocked()->between($this->user1, $this->user3)->create();
        $this->assertFalse(Friendship::canBeFriends($this->user1, $this->user3));

        // Cannot be friends if target doesn't allow friend requests
        $user4 = User::factory()->create();
        $user4->profile->update(['allow_friend_requests' => false]);
        $this->assertFalse(Friendship::canBeFriends($this->user1, $user4));
    }

    public function test_factory_states_work_correctly(): void
    {
        $pending = Friendship::factory()->pending()->create();
        $this->assertEquals(Friendship::STATUS_PENDING, $pending->status);
        $this->assertNull($pending->accepted_at);

        $accepted = Friendship::factory()->accepted()->create();
        $this->assertEquals(Friendship::STATUS_ACCEPTED, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);

        $blocked = Friendship::factory()->blocked()->create();
        $this->assertEquals(Friendship::STATUS_BLOCKED, $blocked->status);
        $this->assertNotNull($blocked->blocked_at);
        $this->assertFalse($blocked->can_see_posts);
        $this->assertFalse($blocked->can_send_messages);

        $declined = Friendship::factory()->declined()->create();
        $this->assertEquals(Friendship::STATUS_DECLINED, $declined->status);
    }

    public function test_high_and_low_mutual_friends_states(): void
    {
        $highMutual = Friendship::factory()->withHighMutualFriends()->create();
        $this->assertGreaterThanOrEqual(20, $highMutual->mutual_friends_count);

        $lowMutual = Friendship::factory()->withLowMutualFriends()->create();
        $this->assertLessThanOrEqual(5, $lowMutual->mutual_friends_count);
    }

    public function test_close_and_distant_friendship_states(): void
    {
        $close = Friendship::factory()->close()->create();
        $this->assertTrue($close->can_see_posts);
        $this->assertTrue($close->can_send_messages);
        $this->assertTrue($close->show_in_friends_list);

        $distant = Friendship::factory()->distant()->create();
        $this->assertLessThanOrEqual(5, $distant->mutual_friends_count);
    }

    public function test_model_events_fire_correctly(): void
    {
        // Test that requested_at is set on creation
        $friendship = Friendship::factory()->make(['requested_at' => null]);
        $this->assertNull($friendship->requested_at);
        
        $friendship->save();
        $this->assertNotNull($friendship->fresh()->requested_at);

        // Test that accepted_at is set when status changes to accepted
        $friendship->update(['status' => Friendship::STATUS_ACCEPTED]);
        $this->assertNotNull($friendship->fresh()->accepted_at);

        // Test that blocked_at is set when status changes to blocked
        $friendship->update(['status' => Friendship::STATUS_BLOCKED]);
        $this->assertNotNull($friendship->fresh()->blocked_at);
    }
} 