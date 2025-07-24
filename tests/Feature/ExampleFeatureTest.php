<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleFeatureTest extends TestCase
{
    /**
     * Test the welcome page loads
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    /**
     * Test that we can access the SPA
     */
    public function test_spa_fallback_route(): void
    {
        $response = $this->get('/some-frontend-route');

        // SPA fallback should return 200 (app.blade.php)
        $response->assertStatus(200);
    }

    /**
     * Test API routes are accessible
     */
    public function test_api_routes_are_accessible(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200);
    }
} 