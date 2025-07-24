<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting AI-Book Social Network Database Seeding...');
        
        // Seed in correct order to maintain referential integrity
        $this->call([
            UserSeeder::class,      // Create users and profiles first
            GroupSeeder::class,     // Create groups and memberships (depends on users)
            PostSeeder::class,      // Create posts and content (depends on users and groups)
            SocialInteractionSeeder::class, // Create friendships, likes, comments, notifications
        ]);
        
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info('📊 Created sample data for AI-Book social networking platform:');
        $this->command->info('   • Users with diverse book interests and profiles');
        $this->command->info('   • Book clubs and reading groups');  
        $this->command->info('   • Book reviews, discussions, and social posts');
        $this->command->info('   • Friendships, likes, comments, and notifications');
        $this->command->info('🔐 Login credentials:');
        $this->command->info('   Admin: admin@ai-book.com / password');
        $this->command->info('   Users: emma@example.com, marcus@example.com, etc. / password');
    }
}
