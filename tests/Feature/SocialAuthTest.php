<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;

class SocialAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_redirect_to_google_provider()
    {
        $response = $this->getJson('/api/auth/social/redirect/google');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'redirect_url',
                'provider'
            ]
        ]);
    }

    public function test_redirect_to_github_provider()
    {
        $response = $this->getJson('/api/auth/social/redirect/github');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'redirect_url',
                'provider'
            ]
        ]);
    }

    public function test_redirect_to_unsupported_provider()
    {
        $response = $this->getJson('/api/auth/social/redirect/facebook');
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'OAuth redirection failed'
        ]);
    }

    public function test_social_callback_creates_new_user()
    {
        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('123456');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($socialiteUser);

        $response = $this->getJson('/api/auth/social/callback/google');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'token',
                'token_type',
                'expires_in',
                'provider',
                'is_new_user'
            ]
        ]);
        $response->assertJson([
            'data' => [
                'provider' => 'google',
                'is_new_user' => true
            ]
        ]);
    }

    public function test_social_callback_links_existing_user_by_email()
    {
        $user = User::factory()->make([
            'email' => 'existing@example.com',
            'social_provider' => null,
            'social_provider_id' => null
        ]);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('789012');
        $socialiteUser->shouldReceive('getEmail')->andReturn('existing@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Existing User');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar2.jpg');

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($socialiteUser);

        $response = $this->getJson('/api/auth/social/callback/github');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'token',
                'provider',
                'is_new_user'
            ]
        ]);
    }

    public function test_get_social_account_info_when_linked()
    {
        $user = User::factory()->make([
            'social_provider' => 'google',
            'social_provider_id' => '123456',
            'social_avatar_url' => 'https://example.com/avatar.jpg',
            'password' => 'hashed_password'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/social/account');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'has_social_account' => true,
                'provider' => 'google',
                'provider_id' => '123456',
                'social_avatar_url' => 'https://example.com/avatar.jpg',
                'can_unlink' => true
            ]
        ]);
    }

    public function test_get_social_account_info_when_not_linked()
    {
        $user = User::factory()->make([
            'social_provider' => null,
            'social_provider_id' => null,
            'social_avatar_url' => null,
            'password' => 'hashed_password'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/social/account');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'has_social_account' => false,
                'provider' => null,
                'provider_id' => null,
                'social_avatar_url' => null,
                'can_unlink' => true
            ]
        ]);
    }

    public function test_unlink_social_account_successfully()
    {
        $user = User::factory()->make([
            'social_provider' => 'google',
            'social_provider_id' => '123456',
            'social_avatar_url' => 'https://example.com/avatar.jpg',
            'password' => 'hashed_password'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/social/unlink');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Social account unlinked successfully',
            'data' => [
                'provider' => 'google'
            ]
        ]);
    }

    public function test_unlink_social_account_without_password_fails()
    {
        $user = User::factory()->make([
            'social_provider' => 'google',
            'social_provider_id' => '123456',
            'social_avatar_url' => 'https://example.com/avatar.jpg',
            'password' => null // No password set
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/social/unlink');
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Cannot unlink social account',
            'errors' => [
                'security' => ['Please set a password before unlinking your social account.']
            ]
        ]);
    }

    public function test_unlink_when_no_social_account_linked()
    {
        $user = User::factory()->make([
            'social_provider' => null,
            'social_provider_id' => null,
            'password' => 'hashed_password'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/auth/social/unlink');
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'No social account linked',
            'errors' => [
                'provider' => ['No social account is currently linked.']
            ]
        ]);
    }

    public function test_link_social_account_to_existing_user()
    {
        $user = User::factory()->make([
            'social_provider' => null,
            'social_provider_id' => null
        ]);
        
        Sanctum::actingAs($user);

        $socialiteUser = Mockery::mock(SocialiteUser::class);
        $socialiteUser->shouldReceive('getId')->andReturn('newid123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('user@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('User Name');
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://example.com/new-avatar.jpg');

        Socialite::shouldReceive('driver')
            ->with('github')
            ->andReturnSelf();
        Socialite::shouldReceive('user')
            ->andReturn($socialiteUser);

        $response = $this->postJson('/api/auth/social/link/github');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Social account linked successfully',
            'data' => [
                'provider' => 'github',
                'provider_name' => 'User Name',
                'provider_email' => 'user@example.com'
            ]
        ]);
    }

    public function test_link_already_linked_provider_fails()
    {
        $user = User::factory()->make([
            'social_provider' => 'google',
            'social_provider_id' => '123456'
        ]);
        
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/auth/social/link/google');
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Account already linked to this provider',
            'errors' => [
                'provider' => ['This social account is already linked.']
            ]
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
} 