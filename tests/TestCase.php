<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected bool $seed = false;

    /**
     * Setup for tests
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Disable external HTTP requests by default
        $this->withoutVite();
        
        // Clear any cached data
        $this->artisan('cache:clear');
        $this->artisan('config:clear');
    }

    /**
     * Get application timezone for tests
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Override with test database settings
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Override mail settings for testing
        $app['config']->set('mail.default', 'array');
        
        // Override cache settings for testing
        $app['config']->set('cache.default', 'array');
        
        // Override session settings for testing
        $app['config']->set('session.driver', 'array');
        
        // Override queue settings for testing
        $app['config']->set('queue.default', 'sync');
    }
}
