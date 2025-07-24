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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            
            // Notification recipient
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Notification trigger (who caused this notification)
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('cascade');
            
            // Notification type and content
            $table->string('type', 100); // like_post, comment_post, friend_request, group_invite, etc.
            $table->text('title');
            $table->text('message');
            $table->string('action_url')->nullable(); // URL to redirect when notification is clicked
            
            // Related entity (polymorphic)
            $table->morphs('notifiable'); // Can relate to posts, comments, groups, etc.
            
            // Notification metadata
            $table->json('data')->nullable(); // Additional context data
            
            // Notification status
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_dismissed')->default(false);
            $table->boolean('is_sent_email')->default(false);
            $table->boolean('is_sent_push')->default(false);
            
            // Priority for sorting/filtering
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes for notification queries
            $table->index(['user_id', 'read_at', 'created_at']); // Unread notifications first
            $table->index(['user_id', 'type', 'created_at']); // Notifications by type
            $table->index(['actor_id', 'created_at']); // Notifications caused by specific user
            $table->index(['priority', 'created_at']); // High priority notifications
            
            // Composite indexes for complex queries
            $table->index(['user_id', 'is_dismissed', 'read_at', 'created_at']); // Active unread notifications
            $table->index(['user_id', 'type', 'read_at', 'created_at']); // Type-specific unread notifications
            $table->index(['actor_id', 'type', 'created_at']); // Activity by user and type
            $table->index(['user_id', 'priority', 'read_at']); // Priority-based notification feeds
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
