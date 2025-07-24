<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PrivacySettingsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_user_can_get_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/privacy');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'profile_privacy' => [
                        'is_private_profile',
                        'profile_visibility',
                        'contact_info_visibility',
                        'location_visibility',
                        'birth_date_visibility',
                        'search_visibility',
                    ],
                    'activity_privacy' => [
                        'show_reading_activity',
                        'show_online_status',
                        'show_last_activity',
                        'reading_activity_visibility',
                        'post_visibility_default',
                    ],
                    'social_privacy' => [
                        'show_friends_list',
                        'show_mutual_friends',
                        'friends_list_visibility',
                        'who_can_see_posts',
                        'who_can_tag_me',
                    ],
                    'interaction_privacy' => [
                        'allow_friend_requests',
                        'allow_group_invites',
                        'allow_book_recommendations',
                        'allow_messages_from',
                        'friend_request_visibility',
                        'who_can_find_me',
                    ],
                    'content_privacy' => [
                        'book_lists_visibility',
                        'reviews_visibility',
                        'reading_goals_visibility',
                        'reading_history_visibility',
                    ],
                ],
            ]);
    }

    public function test_user_can_update_profile_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $privacyData = [
            'profile_privacy' => [
                'is_private_profile' => true,
                'profile_visibility' => 'friends',
                'contact_info_visibility' => 'private',
                'location_visibility' => 'friends',
                'search_visibility' => 'friends',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $privacyData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
            ]);

        // Verify settings were saved
        $user->refresh();
        $this->assertTrue($user->profile->is_private_profile);
        $this->assertEquals('friends', $user->profile->profile_visibility);
        $this->assertEquals('private', $user->profile->contact_info_visibility);
        $this->assertEquals('friends', $user->profile->location_visibility);
        $this->assertEquals('friends', $user->profile->search_visibility);
    }

    public function test_user_can_update_activity_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $privacyData = [
            'activity_privacy' => [
                'show_reading_activity' => false,
                'show_online_status' => false,
                'show_last_activity' => false,
                'reading_activity_visibility' => 'private',
                'post_visibility_default' => 'close_friends',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $privacyData);

        $response->assertOk();

        $user->refresh();
        $this->assertFalse($user->profile->show_reading_activity);
        $this->assertFalse($user->profile->show_online_status);
        $this->assertFalse($user->profile->show_last_activity);
        $this->assertEquals('private', $user->profile->reading_activity_visibility);
        $this->assertEquals('close_friends', $user->profile->post_visibility_default);
    }

    public function test_user_can_update_social_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $privacyData = [
            'social_privacy' => [
                'show_friends_list' => false,
                'show_mutual_friends' => false,
                'friends_list_visibility' => 'private',
                'who_can_see_posts' => 'friends',
                'who_can_tag_me' => 'nobody',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $privacyData);

        $response->assertOk();

        $user->refresh();
        $this->assertFalse($user->profile->show_friends_list);
        $this->assertFalse($user->profile->show_mutual_friends);
        $this->assertEquals('private', $user->profile->friends_list_visibility);
        $this->assertEquals('friends', $user->profile->who_can_see_posts);
        $this->assertEquals('nobody', $user->profile->who_can_tag_me);
    }

    public function test_user_can_update_interaction_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $privacyData = [
            'interaction_privacy' => [
                'allow_friend_requests' => false,
                'allow_group_invites' => false,
                'allow_book_recommendations' => false,
                'allow_messages_from' => 'friends',
                'friend_request_visibility' => 'nobody',
                'who_can_find_me' => 'friends',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $privacyData);

        $response->assertOk();

        $user->refresh();
        $this->assertFalse($user->profile->allow_friend_requests);
        $this->assertFalse($user->profile->allow_group_invites);
        $this->assertFalse($user->profile->allow_book_recommendations);
        $this->assertEquals('friends', $user->profile->allow_messages_from);
        $this->assertEquals('nobody', $user->profile->friend_request_visibility);
        $this->assertEquals('friends', $user->profile->who_can_find_me);
    }

    public function test_user_can_update_content_privacy_settings()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $privacyData = [
            'content_privacy' => [
                'book_lists_visibility' => 'private',
                'reviews_visibility' => 'friends',
                'reading_goals_visibility' => 'private',
                'reading_history_visibility' => 'friends',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $privacyData);

        $response->assertOk();

        $user->refresh();
        $this->assertEquals('private', $user->profile->book_lists_visibility);
        $this->assertEquals('friends', $user->profile->reviews_visibility);
        $this->assertEquals('private', $user->profile->reading_goals_visibility);
        $this->assertEquals('friends', $user->profile->reading_history_visibility);
    }

    public function test_privacy_settings_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Test invalid visibility value
        $invalidData = [
            'profile_privacy' => [
                'profile_visibility' => 'invalid_value',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $invalidData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_privacy.profile_visibility']);

        // Test invalid boolean value
        $invalidBooleanData = [
            'activity_privacy' => [
                'show_reading_activity' => 'invalid_boolean',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $invalidBooleanData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['activity_privacy.show_reading_activity']);
    }

    public function test_privacy_logic_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Test that when friend requests are disabled, friend request visibility should be nobody
        $logicallyInconsistentData = [
            'interaction_privacy' => [
                'allow_friend_requests' => false,
                'friend_request_visibility' => 'everyone',
            ],
        ];

        $response = $this->putJson('/api/users/privacy', $logicallyInconsistentData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['interaction_privacy.friend_request_visibility']);
    }

    public function test_user_can_get_privacy_options()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/privacy/options');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'visibility_levels',
                    'who_can_options',
                    'post_visibility_options',
                    'search_visibility_options',
                ],
            ]);
    }

    public function test_user_can_check_privacy_access()
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/users/privacy/check-access', [
            'target_user_id' => $targetUser->id,
            'content_type' => 'profile',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'has_access',
                    'content_type',
                    'target_user_id',
                    'relationship',
                ],
            ]);
    }

    public function test_privacy_access_validation()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Test missing required fields
        $response = $this->postJson('/api/users/privacy/check-access', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_user_id', 'content_type']);

        // Test invalid content type
        $response = $this->postJson('/api/users/privacy/check-access', [
            'target_user_id' => $user->id,
            'content_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content_type']);
    }

    public function test_user_can_get_privacy_audit_log()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/users/privacy/audit-log');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recent_changes',
                    'privacy_score',
                    'recommendations',
                ],
            ]);
    }

    public function test_privacy_endpoints_require_authentication()
    {
        $endpoints = [
            ['GET', '/api/users/privacy'],
            ['PUT', '/api/users/privacy'],
            ['GET', '/api/users/privacy/options'],
            ['POST', '/api/users/privacy/check-access'],
            ['GET', '/api/users/privacy/audit-log'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            $response->assertStatus(401);
        }
    }

    public function test_privacy_settings_affect_profile_visibility()
    {
        // Create users
        $privateUser = User::factory()->create();
        $viewingUser = User::factory()->create();

        // Set private user's profile to private
        $privateUser->profile->update([
            'is_private_profile' => true,
            'contact_info_visibility' => 'friends',
            'location' => 'Secret Location',
            'phone' => '123-456-7890',
        ]);

        $this->actingAs($viewingUser, 'sanctum');

        // Try to view private user's profile
        $response = $this->getJson("/api/users/profile/{$privateUser->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Profile is private',
            ]);
    }

    public function test_friends_can_view_private_profiles()
    {
        // Create users
        $privateUser = User::factory()->create();
        $friendUser = User::factory()->create();

        // Make them friends (simplified - normally would go through friend request process)
        $privateUser->sentFriendRequests()->create([
            'friend_id' => $friendUser->id,
            'status' => 'accepted',
            'requested_at' => now(),
            'accepted_at' => now(),
        ]);

        // Set private user's profile settings
        $privateUser->profile->update([
            'is_private_profile' => true,
            'contact_info_visibility' => 'friends',
            'location' => 'Secret Location',
            'phone' => '123-456-7890',
        ]);

        $this->actingAs($friendUser, 'sanctum');

        // Friend should be able to view private profile
        $response = $this->getJson("/api/users/profile/{$privateUser->id}");

        $response->assertOk()
            ->assertJsonFragment(['location' => 'Secret Location']);
    }

    public function test_field_level_privacy_is_respected()
    {
        // Create users who are friends
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Make them friends
        $user1->sentFriendRequests()->create([
            'friend_id' => $user2->id,
            'status' => 'accepted',
            'requested_at' => now(),
            'accepted_at' => now(),
        ]);

        // Set specific field privacy
        $user1->profile->update([
            'is_private_profile' => false, // Profile is public
            'location' => 'Public Location',
            'phone' => '123-456-7890',
            'contact_info_visibility' => 'private', // But contact info is private
        ]);

        $this->actingAs($user2, 'sanctum');

        $response = $this->getJson("/api/users/profile/{$user1->id}");

        $response->assertOk()
            ->assertJsonFragment(['location' => 'Public Location']) // Location visible
            ->assertJsonPath('data.user.profile.phone', null); // Phone hidden due to contact_info_visibility
    }
} 