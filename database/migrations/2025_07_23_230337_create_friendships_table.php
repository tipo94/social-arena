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
        Schema::create('friendships', function (Blueprint $table) {
            $table->id();
            
            // Friend relationship participants
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Person who sent the request
            $table->foreignId('friend_id')->constrained('users')->onDelete('cascade'); // Person who received the request
            
            // Friendship status
            $table->enum('status', ['pending', 'accepted', 'blocked', 'declined'])->default('pending');
            
            // Timestamps for different stages
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            
            // Friendship settings
            $table->boolean('can_see_posts')->default(true);
            $table->boolean('can_send_messages')->default(true);
            $table->boolean('show_in_friends_list')->default(true);
            
            // Mutual friend tracking (denormalized for performance)
            $table->unsignedInteger('mutual_friends_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Ensure no duplicate friendships (bidirectional constraint)
            $table->unique(['user_id', 'friend_id'], 'unique_friendship');
            
            // Performance indexes for social networking queries
            $table->index(['user_id', 'status', 'accepted_at']); // User's friends by status
            $table->index(['friend_id', 'status', 'accepted_at']); // Incoming friend requests
            $table->index(['status', 'requested_at']); // Pending requests by date
            $table->index(['user_id', 'status', 'show_in_friends_list']); // Visible friends
            $table->index(['friend_id', 'status', 'show_in_friends_list']); // Reverse visible friends
            
            // Composite indexes for complex queries
            $table->index(['user_id', 'friend_id', 'status']); // Quick friendship lookup
            $table->index(['status', 'accepted_at', 'mutual_friends_count']); // Friend suggestions
            $table->index(['user_id', 'status', 'can_see_posts']); // Content visibility checks
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
