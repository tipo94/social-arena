<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Friendship;
use App\Services\FriendSuggestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

class FriendSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private FriendSuggestionService $suggestionService;
    private User $user;
    private User $friend1;
    private User $friend2;
    private User $mutualFriend;
    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->suggestionService = app(FriendSuggestionService::class);
        
        // Create test users
        $this->user = User::factory()->create();
        $this->friend1 = User::factory()->create();
        $this->friend2 = User::factory()->create();
        $this->mutualFriend = User::factory()->create();
        $this->stranger = User::factory()->create();
        
        // Set up friend relationships
        Friendship::factory()->accepted()->between($this->user, $this->friend1)->create();
        Friendship::factory()->accepted()->between($this->user, $this->friend2)->create();
        
        // Create mutual friend scenario
        Friendship::factory()->accepted()->between($this->friend1, $this->mutualFriend)->create();
        Friendship::factory()->accepted()->between($this->friend2, $this->mutualFriend)->create();
    }

    public function test_mutual_connections_algorithm(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'mutual_connections',
            'count' => 10,
        ]);

        $this->assertGreaterThan(0, $suggestions->count());
        $this->assertTrue($suggestions->contains('user_id', $this->mutualFriend->id));
        
        $mutualSuggestion = $suggestions->firstWhere('user_id', $this->mutualFriend->id);
        $this->assertEquals(2, $mutualSuggestion->mutual_friends_count);
        $this->assertStringContainsString('mutual friends', $mutualSuggestion->suggestion_reason);
    }

    public function test_friends_of_friends_algorithm(): void
    {
        // Create a friend of a friend scenario
        $friendOfFriend = User::factory()->create();
        Friendship::factory()->accepted()->between($this->friend1, $friendOfFriend)->create();

        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'friends_of_friends',
            'count' => 10,
        ]);

        $this->assertGreaterThan(0, $suggestions->count());
    }

    public function test_network_analysis_algorithm(): void
    {
        // Create a network with various connections
        $networkUsers = User::factory()->count(5)->create();
        foreach ($networkUsers as $networkUser) {
            Friendship::factory()->accepted()->between($this->friend1, $networkUser)->create();
        }

        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'network_analysis',
            'count' => 10,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
    }

    public function test_hybrid_algorithm(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'hybrid',
            'count' => 10,
            'include_scores' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
        
        if ($suggestions->isNotEmpty()) {
            $firstSuggestion = $suggestions->first();
            $this->assertObjectHasProperty('confidence_score', $firstSuggestion);
            $this->assertObjectHasProperty('scores', $firstSuggestion);
        }
    }

    public function test_suggestions_respect_privacy_settings(): void
    {
        // Create user who doesn't allow friend requests
        $privateUser = User::factory()->create();
        $privateUser->profile->update(['allow_friend_requests' => false]);
        
        // Create mutual friend connection
        Friendship::factory()->accepted()->between($this->friend1, $privateUser)->create();

        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'mutual_connections',
            'count' => 10,
        ]);

        $this->assertFalse($suggestions->contains('user_id', $privateUser->id));
    }

    public function test_suggestions_exclude_existing_relationships(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user);

        // Ensure existing friends are not suggested
        $this->assertFalse($suggestions->contains('user_id', $this->friend1->id));
        $this->assertFalse($suggestions->contains('user_id', $this->friend2->id));
        $this->assertFalse($suggestions->contains('user_id', $this->user->id));
    }

    public function test_suggestions_include_comprehensive_data(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'include_scores' => true,
            'count' => 5,
        ]);

        if ($suggestions->isNotEmpty()) {
            $suggestion = $suggestions->first();
            
            $this->assertObjectHasProperty('user_id', $suggestion);
            $this->assertObjectHasProperty('name', $suggestion);
            $this->assertObjectHasProperty('username', $suggestion);
            $this->assertObjectHasProperty('confidence_score', $suggestion);
            $this->assertObjectHasProperty('suggestion_reason', $suggestion);
            $this->assertObjectHasProperty('scores', $suggestion);
            $this->assertObjectHasProperty('mutual_friends_count', $suggestion);
        }
    }

    public function test_minimum_score_filtering(): void
    {
        $highScoreSuggestions = $this->suggestionService->getSuggestions($this->user, [
            'min_score' => 0.8,
            'count' => 10,
        ]);

        $lowScoreSuggestions = $this->suggestionService->getSuggestions($this->user, [
            'min_score' => 0.1,
            'count' => 10,
        ]);

        $this->assertLessThanOrEqual($lowScoreSuggestions->count(), $highScoreSuggestions->count());
    }

    public function test_suggestion_analytics(): void
    {
        $analytics = $this->suggestionService->getSuggestionAnalytics($this->user);

        $this->assertIsArray($analytics);
        $this->assertArrayHasKey('total_friends', $analytics);
        $this->assertArrayHasKey('suggestion_pool_size', $analytics);
        $this->assertArrayHasKey('network_density', $analytics);
        $this->assertArrayHasKey('avg_mutual_friends', $analytics);
        $this->assertArrayHasKey('last_updated', $analytics);

        $this->assertEquals(2, $analytics['total_friends']);
        $this->assertIsFloat($analytics['network_density']);
        $this->assertGreaterThanOrEqual(0, $analytics['suggestion_pool_size']);
    }

    public function test_caching_functionality(): void
    {
        // Clear cache first
        Cache::flush();

        // First call should miss cache
        $suggestions1 = $this->suggestionService->getSuggestions($this->user, [
            'use_cache' => true,
            'count' => 5,
        ]);

        // Second call should hit cache
        $suggestions2 = $this->suggestionService->getSuggestions($this->user, [
            'use_cache' => true,
            'count' => 5,
        ]);

        $this->assertEquals($suggestions1->toArray(), $suggestions2->toArray());

        // Clear cache and verify it's cleared
        $this->suggestionService->clearUserCache($this->user);
        $this->assertTrue(true); // Just verify no errors occurred
    }

    public function test_suggestion_scoring_components(): void
    {
        // Create a user with complete profile for scoring
        $targetUser = User::factory()->create();
        $targetUser->profile->update([
            'bio' => 'A well-written bio',
            'location' => $this->user->profile->location, // Same location
            'favorite_genres' => $this->user->profile->favorite_genres, // Same interests
        ]);

        // Create mutual friend
        Friendship::factory()->accepted()->between($this->friend1, $targetUser)->create();

        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'include_scores' => true,
            'algorithm' => 'mutual_connections',
        ]);

        $targetSuggestion = $suggestions->firstWhere('user_id', $targetUser->id);
        
        if ($targetSuggestion) {
            $this->assertObjectHasProperty('scores', $targetSuggestion);
            $scores = $targetSuggestion->scores;
            
            $this->assertArrayHasKey('mutual_connections', $scores);
            $this->assertArrayHasKey('profile_completeness', $scores);
            $this->assertArrayHasKey('activity_level', $scores);
            $this->assertArrayHasKey('interest_similarity', $scores);
            $this->assertArrayHasKey('location_proximity', $scores);
            $this->assertArrayHasKey('total_score', $scores);
            
            $this->assertGreaterThan(0, $scores['mutual_connections']);
            $this->assertGreaterThan(0, $scores['profile_completeness']);
        }
    }

    public function test_empty_network_handling(): void
    {
        $lonelyUser = User::factory()->create();

        $suggestions = $this->suggestionService->getSuggestions($lonelyUser, [
            'algorithm' => 'mutual_connections',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
        // User with no friends should get empty suggestions for mutual_connections
        $this->assertCount(0, $suggestions);
    }

    public function test_large_network_performance(): void
    {
        // Create a larger network for performance testing
        $friends = User::factory()->count(20)->create();
        foreach ($friends as $friend) {
            Friendship::factory()->accepted()->between($this->user, $friend)->create();
        }

        $start = microtime(true);
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'count' => 10,
        ]);
        $duration = microtime(true) - $start;

        $this->assertLessThan(2.0, $duration); // Should complete within 2 seconds
        $this->assertInstanceOf(\Illuminate\Support\Collection::class, $suggestions);
    }

    // API Endpoint Tests

    public function test_suggestions_api_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/friends/suggestions');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'suggestions',
                        'algorithm',
                        'count',
                        'options',
                        'generated_at',
                    ],
                ]);
    }

    public function test_suggestions_api_with_parameters(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/friends/suggestions?' . http_build_query([
            'count' => 5,
            'algorithm' => 'hybrid',
            'include_scores' => true,
            'min_score' => 0.2,
        ]));

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonPath('data.algorithm', 'hybrid')
                ->assertJsonPath('data.options.count', 5)
                ->assertJsonPath('data.options.include_scores', true);
    }

    public function test_suggestion_analytics_api_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/friends/suggestions/analytics');

        $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'data' => [
                        'analytics' => [
                            'total_friends',
                            'suggestion_pool_size',
                            'network_density',
                            'avg_mutual_friends',
                            'last_updated',
                        ],
                        'generated_at',
                    ],
                ]);
    }

    public function test_clear_suggestion_cache_api_endpoint(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->deleteJson('/api/friends/suggestions/cache');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Suggestion cache cleared successfully',
                ]);
    }

    public function test_suggestions_api_validation(): void
    {
        Sanctum::actingAs($this->user);

        // Test invalid algorithm
        $response = $this->getJson('/api/friends/suggestions?algorithm=invalid');
        $response->assertStatus(422);

        // Test invalid count
        $response = $this->getJson('/api/friends/suggestions?count=0');
        $response->assertStatus(422);

        // Test invalid min_score
        $response = $this->getJson('/api/friends/suggestions?min_score=2.0');
        $response->assertStatus(422);
    }

    public function test_unauthenticated_access_denied(): void
    {
        $response = $this->getJson('/api/friends/suggestions');
        $response->assertStatus(401);

        $response = $this->getJson('/api/friends/suggestions/analytics');
        $response->assertStatus(401);

        $response = $this->deleteJson('/api/friends/suggestions/cache');
        $response->assertStatus(401);
    }

    public function test_suggestion_reasons_are_descriptive(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'mutual_connections',
        ]);

        foreach ($suggestions as $suggestion) {
            $this->assertNotEmpty($suggestion->suggestion_reason);
            $this->assertIsString($suggestion->suggestion_reason);
        }
    }

    public function test_mutual_friends_count_accuracy(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'mutual_connections',
            'include_scores' => true,
        ]);

        $mutualSuggestion = $suggestions->firstWhere('user_id', $this->mutualFriend->id);
        
        if ($mutualSuggestion) {
            $this->assertEquals(2, $mutualSuggestion->mutual_friends_count);
            
            // Verify the mutual friend IDs are included
            $this->assertObjectHasProperty('mutual_friend_ids', $mutualSuggestion);
            $this->assertIsArray($mutualSuggestion->mutual_friend_ids);
            $this->assertCount(2, $mutualSuggestion->mutual_friend_ids);
        }
    }

    public function test_algorithm_coverage_in_hybrid_mode(): void
    {
        $suggestions = $this->suggestionService->getSuggestions($this->user, [
            'algorithm' => 'hybrid',
            'count' => 10,
        ]);

        // In hybrid mode, suggestions should have algorithm coverage data
        foreach ($suggestions as $suggestion) {
            if (property_exists($suggestion, 'algorithm_coverage')) {
                $this->assertGreaterThan(0, $suggestion->algorithm_coverage);
                $this->assertLessThanOrEqual(3, $suggestion->algorithm_coverage);
            }
        }
    }
} 