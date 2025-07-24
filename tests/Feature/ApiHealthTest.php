<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    /**
     * Test the API health endpoint
     */
    public function test_api_health_endpoint(): void
    {
        $response = $this->get('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'message' => 'AI-Book API is running',
                'sanctum' => 'enabled',
            ])
            ->assertJsonStructure([
                'status',
                'message', 
                'timestamp',
                'sanctum',
            ]);
    }

    /**
     * Test CSRF cookie endpoint
     */
    public function test_sanctum_csrf_cookie_endpoint(): void
    {
        $response = $this->get('/api/sanctum/csrf-cookie');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'CSRF cookie set'
            ]);
    }

    /**
     * Test that protected endpoints require authentication
     */
    public function test_protected_endpoints_require_authentication(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    /**
     * Test that test auth endpoint requires authentication
     */
    public function test_auth_test_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/test-auth');

        $response->assertStatus(401);
    }
} 