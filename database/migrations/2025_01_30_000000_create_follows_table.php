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
        Schema::create('follows', function (Blueprint $table) {
            $table->id();
            
            // Follow relationship participants
            $table->foreignId('follower_id')->constrained('users')->onDelete('cascade'); // Person who follows
            $table->foreignId('following_id')->constrained('users')->onDelete('cascade'); // Person being followed
            
            // Follow metadata
            $table->timestamp('followed_at')->useCurrent();
            $table->boolean('is_muted')->default(false); // Can follow but mute their posts
            $table->boolean('show_notifications')->default(true); // Get notified of their posts
            
            // Privacy and interaction settings
            $table->boolean('is_close_friend')->default(false); // Mark as close friend for special content
            $table->json('interaction_preferences')->nullable(); // Custom notification preferences
            
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure a user can't follow someone twice
            $table->unique(['follower_id', 'following_id'], 'unique_follow');
            
            // Performance indexes for social networking queries
            $table->index(['follower_id', 'followed_at']); // User's following list
            $table->index(['following_id', 'followed_at']); // User's followers list
            $table->index(['follower_id', 'is_muted', 'followed_at']); // Non-muted follows for feed
            $table->index(['following_id', 'show_notifications', 'followed_at']); // Notification-enabled followers
            $table->index(['follower_id', 'is_close_friend', 'followed_at']); // Close friends filtering
            
            // Composite indexes for analytics and recommendations
            $table->index(['followed_at', 'follower_id']); // Recent follows
            $table->index(['followed_at', 'following_id']); // Recently followed users
            $table->index(['is_muted', 'show_notifications', 'followed_at']); // Active follows
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
}; 