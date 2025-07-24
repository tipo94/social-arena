<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

class UserProfileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_user_can_view_own_profile()
    {
        $user = User::factory()->make([
            'name' => 'John Doe',
            'username' => 'johndoe',
            'email' => 'john@example.com'
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/profile');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'username',
                    'email',
                    'profile'
                ],
                'can_edit',
                'is_friend'
            ]
        ]);
        $response->assertJson([
            'data' => [
                'can_edit' => true,
                'is_friend' => false
            ]
        ]);
    }

    public function test_user_can_view_public_profile()
    {
        $currentUser = User::factory()->make(['id' => 1]);
        $profileUser = User::factory()->make([
            'id' => 2,
            'name' => 'Jane Doe',
            'username' => 'janedoe'
        ]);

        // Mock the profile relationship
        $profileUser->setRelation('profile', UserProfile::factory()->make([
            'is_private_profile' => false
        ]));

        Sanctum::actingAs($currentUser);

        $response = $this->getJson('/api/users/profile/2');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'can_edit' => false,
                'is_friend' => false
            ]
        ]);
    }

    public function test_user_cannot_view_private_profile_without_friendship()
    {
        $currentUser = User::factory()->make(['id' => 1]);
        $profileUser = User::factory()->make([
            'id' => 2,
            'name' => 'Private User'
        ]);

        // Mock the profile relationship as private
        $profileUser->setRelation('profile', UserProfile::factory()->make([
            'is_private_profile' => true
        ]));

        Sanctum::actingAs($currentUser);

        $response = $this->getJson('/api/users/profile/2');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'message' => 'Profile is private'
        ]);
    }

    public function test_user_can_update_basic_profile_information()
    {
        $user = User::factory()->make([
            'name' => 'Original Name',
            'username' => 'original'
        ]);

        Sanctum::actingAs($user);

        $updateData = [
            'name' => 'Updated Name',
            'bio' => 'This is my updated bio',
            'location' => 'New York, NY',
            'website' => 'https://example.com',
            'occupation' => 'Software Developer'
        ];

        $response = $this->putJson('/api/users/profile', $updateData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    }

    public function test_user_can_upload_avatar()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $response = $this->postJson('/api/users/profile/avatar', [
            'avatar' => $file
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Avatar updated successfully'
        ]);
        $response->assertJsonStructure([
            'data' => [
                'avatar_url',
                'completion_percentage'
            ]
        ]);
    }

    public function test_avatar_upload_validates_file_type()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('document.pdf', 1000);

        $response = $this->postJson('/api/users/profile/avatar', [
            'avatar' => $file
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_avatar_upload_validates_file_size()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Create a file larger than 2MB
        $file = UploadedFile::fake()->image('huge-avatar.jpg')->size(3000);

        $response = $this->postJson('/api/users/profile/avatar', [
            'avatar' => $file
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['avatar']);
    }

    public function test_user_can_delete_avatar()
    {
        $user = User::factory()->make();
        $user->setRelation('profile', UserProfile::factory()->make([
            'avatar_url' => 'https://example.com/avatar.jpg'
        ]));

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/users/profile/avatar');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Avatar deleted successfully'
        ]);
    }

    public function test_user_can_upload_cover_image()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('cover.jpg', 1200, 400);

        $response = $this->postJson('/api/users/profile/cover-image', [
            'cover_image' => $file
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Cover image updated successfully'
        ]);
        $response->assertJsonStructure([
            'data' => ['cover_image_url']
        ]);
    }

    public function test_user_can_update_interests()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $interestsData = [
            'reading_preferences' => [
                'favorite_genres' => ['fiction', 'mystery', 'science_fiction'],
                'reading_goals' => 'book_per_month',
                'books_per_year_goal' => 24,
                'favorite_authors' => ['Agatha Christie', 'Isaac Asimov']
            ],
            'book_club_interests' => [
                'interested_in_clubs' => true,
                'preferred_meeting_frequency' => 'monthly',
                'preferred_genres' => ['mystery', 'fiction']
            ],
            'hobbies' => ['reading', 'writing', 'traveling']
        ];

        $response = $this->putJson('/api/users/profile/interests', $interestsData);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Interests updated successfully'
        ]);
    }

    public function test_interests_validation_limits_genres()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Try to add more than 10 favorite genres
        $tooManyGenres = [
            'fiction', 'non_fiction', 'mystery', 'romance', 'science_fiction',
            'fantasy', 'thriller', 'biography', 'history', 'self_help',
            'business' // This is the 11th genre
        ];

        $interestsData = [
            'reading_preferences' => [
                'favorite_genres' => $tooManyGenres
            ]
        ];

        $response = $this->putJson('/api/users/profile/interests', $interestsData);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reading_preferences.favorite_genres']);
    }

    public function test_user_can_get_interests()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/profile/interests');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'reading_preferences',
                'available_genres',
                'available_reading_goals'
            ]
        ]);
    }

    public function test_user_can_get_profile_completion_status()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/users/profile/completion');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'completion_percentage',
                'missing_fields',
                'suggestions'
            ]
        ]);
    }

    public function test_user_can_search_other_users()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        $searchParams = [
            'query' => 'john',
            'location' => 'New York',
            'per_page' => 10
        ];

        $response = $this->getJson('/api/users/search?' . http_build_query($searchParams));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'users',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total'
                ]
            ]
        ]);
    }

    public function test_search_validates_pagination_limits()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Try to request more than 50 users per page
        $response = $this->getJson('/api/users/search?per_page=100');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['per_page']);
    }

    public function test_profile_update_validates_required_fields()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Test invalid website URL
        $response = $this->putJson('/api/users/profile', [
            'website' => 'not-a-url'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['website']);

        // Test invalid birth date (in the future)
        $response = $this->putJson('/api/users/profile', [
            'birth_date' => now()->addYear()->format('Y-m-d')
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['birth_date']);
    }

    public function test_profile_update_validates_username_uniqueness()
    {
        $user1 = User::factory()->make(['id' => 1, 'username' => 'user1']);
        $user2 = User::factory()->make(['id' => 2, 'username' => 'user2']);

        Sanctum::actingAs($user2);

        // Try to change username to one that already exists
        $response = $this->putJson('/api/users/profile', [
            'username' => 'user1'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['username']);
    }

    public function test_social_links_validation()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Test invalid Twitter URL
        $response = $this->putJson('/api/users/profile', [
            'social_links' => [
                'twitter' => 'https://facebook.com/user'  // Wrong domain for Twitter
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['social_links.twitter']);

        // Test valid social links
        $response = $this->putJson('/api/users/profile', [
            'social_links' => [
                'twitter' => 'https://twitter.com/user',
                'instagram' => 'https://instagram.com/user',
                'linkedin' => 'https://linkedin.com/in/user'
            ]
        ]);

        $response->assertStatus(200);
    }

    public function test_book_club_interests_validation()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Test that meeting frequency is required if interested in clubs
        $response = $this->putJson('/api/users/profile/interests', [
            'book_club_interests' => [
                'interested_in_clubs' => true
                // Missing preferred_meeting_frequency
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['book_club_interests.preferred_meeting_frequency']);
    }

    public function test_writing_interests_validation()
    {
        $user = User::factory()->make();
        Sanctum::actingAs($user);

        // Test that writing genres are required if user is a writer
        $response = $this->putJson('/api/users/profile/interests', [
            'writing_interests' => [
                'is_writer' => true
                // Missing writing_genres
            ]
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['writing_interests.writing_genres']);
    }

    protected function tearDown(): void
    {
        Storage::fake('public');
        parent::tearDown();
    }
} 