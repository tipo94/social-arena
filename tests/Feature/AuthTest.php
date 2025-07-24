<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    /**
     * Test user registration with valid data.
     */
    public function test_user_registration_with_valid_data(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'username' => 'johndoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'username',
                        'token',
                        'token_type',
                        'expires_in',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com',
            'username' => 'johndoe',
        ]);

        // Check if profile was created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertNotNull($user->profile);
    }

    /**
     * Test user registration with duplicate email.
     */
    public function test_user_registration_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test user registration with weak password.
     */
    public function test_user_registration_with_weak_password(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => '123456',
            'password_confirmation' => '123456',
            'terms_accepted' => true,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test user registration without accepting terms.
     */
    public function test_user_registration_without_accepting_terms(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
            'terms_accepted' => false,
            'privacy_accepted' => true,
        ];

        $response = $this->postJson('/api/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted']);
    }

    /**
     * Test successful user login.
     */
    public function test_successful_user_login(): void
    {
        $user = User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        // Check if login statistics were updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertEquals(1, $user->login_count);
    }

    /**
     * Test login with invalid credentials.
     */
    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'john@example.com',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $loginData = [
            'email' => 'john@example.com',
            'password' => 'WrongPassword',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test login with banned user.
     */
    public function test_login_with_banned_user(): void
    {
        $user = User::factory()->banned()->create([
            'email' => 'banned@example.com',
            'password' => Hash::make('SecurePassword123!'),
        ]);

        $loginData = [
            'email' => 'banned@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is banned',
            ]);
    }

    /**
     * Test login with inactive user.
     */
    public function test_login_with_inactive_user(): void
    {
        $user = User::factory()->create([
            'email' => 'inactive@example.com',
            'password' => Hash::make('SecurePassword123!'),
            'is_active' => false,
        ]);

        $loginData = [
            'email' => 'inactive@example.com',
            'password' => 'SecurePassword123!',
        ];

        $response = $this->postJson('/api/auth/login', $loginData);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Account is deactivated',
            ]);
    }

    /**
     * Test user logout.
     */
    public function test_user_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Verify token was deleted
        $this->assertEquals(0, $user->tokens()->count());
    }

    /**
     * Test get authenticated user.
     */
    public function test_get_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'username',
                    ],
                    'stats',
                ],
            ]);
    }

    /**
     * Test username availability check.
     */
    public function test_username_availability_check(): void
    {
        User::factory()->create(['username' => 'johndoe']);

        // Test existing username
        $response = $this->postJson('/api/auth/check-username', [
            'username' => 'johndoe',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'username' => 'johndoe',
                    'available' => false,
                ],
            ]);

        // Test available username
        $response = $this->postJson('/api/auth/check-username', [
            'username' => 'janedoe',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'username' => 'janedoe',
                    'available' => true,
                ],
            ]);
    }

    /**
     * Test email availability check.
     */
    public function test_email_availability_check(): void
    {
        User::factory()->create(['email' => 'john@example.com']);

        // Test existing email
        $response = $this->postJson('/api/auth/check-email', [
            'email' => 'john@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'john@example.com',
                    'available' => false,
                ],
            ]);

        // Test available email
        $response = $this->postJson('/api/auth/check-email', [
            'email' => 'jane@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'email' => 'jane@example.com',
                    'available' => true,
                ],
            ]);
    }

    /**
     * Test password change.
     */
    public function test_password_change(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/change-password', [
            'current_password' => 'OldPassword123!',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password changed successfully',
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    /**
     * Test password change with wrong current password.
     */
    public function test_password_change_with_wrong_current_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('OldPassword123!'),
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/change-password', [
            'current_password' => 'WrongPassword',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test token refresh.
     */
    public function test_token_refresh(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'expires_in',
                ],
            ]);

        // Verify old token was deleted
        $this->assertEquals(1, $user->tokens()->count());
    }

    /**
     * Test unauthenticated access to protected routes.
     */
    public function test_unauthenticated_access_to_protected_routes(): void
    {
        $response = $this->getJson('/api/auth/me');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/logout');
        $response->assertStatus(401);

        $response = $this->postJson('/api/auth/change-password', [
            'current_password' => 'test',
            'password' => 'test',
            'password_confirmation' => 'test',
        ]);
        $response->assertStatus(401);
    }
} 