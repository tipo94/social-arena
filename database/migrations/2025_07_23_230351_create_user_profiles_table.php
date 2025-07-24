<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            
            // One-to-one relationship with users
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            
            // Personal information
            $table->string('bio', 1000)->nullable();
            $table->string('location', 255)->nullable();
            $table->string('website', 500)->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary', 'prefer_not_to_say', 'other'])->nullable();
            
            // Profile media
            $table->string('avatar_url', 500)->nullable();
            $table->string('cover_image_url', 500)->nullable();
            
            // Reading preferences and interests
            $table->json('favorite_genres')->nullable(); // Array of preferred book genres
            $table->json('favorite_authors')->nullable(); // Array of favorite authors
            $table->json('reading_goals')->nullable(); // Annual reading goals, challenges
            $table->enum('reading_speed', ['slow', 'average', 'fast', 'very_fast'])->nullable();
            $table->json('languages')->nullable(); // Languages user reads in
            
            // Social preferences
            $table->boolean('is_private_profile')->default(false);
            $table->boolean('show_reading_activity')->default(true);
            $table->boolean('show_friends_list')->default(true);
            $table->boolean('allow_friend_requests')->default(true);
            $table->boolean('allow_group_invites')->default(true);
            $table->boolean('allow_book_recommendations')->default(true);
            
            // Notification preferences
            $table->boolean('email_notifications')->default(true);
            $table->boolean('push_notifications')->default(true);
            $table->boolean('notification_likes')->default(true);
            $table->boolean('notification_comments')->default(true);
            $table->boolean('notification_friend_requests')->default(true);
            $table->boolean('notification_group_invites')->default(true);
            $table->boolean('notification_book_recommendations')->default(true);
            $table->boolean('notification_reading_reminders')->default(false);
            
            // Activity statistics (denormalized for performance)
            $table->unsignedInteger('books_read_count')->default(0);
            $table->unsignedInteger('reviews_written_count')->default(0);
            $table->unsignedInteger('friends_count')->default(0);
            $table->unsignedInteger('followers_count')->default(0);
            $table->unsignedInteger('following_count')->default(0);
            $table->unsignedInteger('groups_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            
            // Profile completion and verification
            $table->unsignedTinyInteger('profile_completion_percentage')->default(0);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            
            // Account status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamp('last_profile_update')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['is_private_profile', 'is_active']); // Public profiles
            $table->index(['location', 'is_active']); // Users by location
            $table->index(['is_verified', 'is_active']); // Verified users
            $table->index(['is_featured', 'books_read_count']); // Featured readers
            $table->index(['profile_completion_percentage', 'is_active']); // Profile completion
            
            // Full-text search for user discovery
            // Full-text search capabilities for bio (MySQL/PostgreSQL only)
            if (config('database.default') !== 'sqlite') {
                $table->fullText(['bio'], 'user_profiles_search');
            }
            
            // Composite indexes for complex queries
            $table->index(['is_active', 'is_private_profile', 'friends_count']); // Popular public users
            $table->index(['location', 'is_active', 'allow_friend_requests']); // Local users open to friends
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};
