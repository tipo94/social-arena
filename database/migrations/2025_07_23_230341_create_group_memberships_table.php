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
        Schema::create('group_memberships', function (Blueprint $table) {
            $table->id();
            
            // Membership relationship
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('group_id')->constrained()->onDelete('cascade');
            
            // Membership status and role
            $table->enum('status', ['pending', 'approved', 'declined', 'banned', 'left'])->default('pending');
            $table->enum('role', ['member', 'moderator', 'admin', 'owner'])->default('member');
            
            // Membership timeline
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Member permissions (for granular control)
            $table->boolean('can_post')->default(true);
            $table->boolean('can_comment')->default(true);
            $table->boolean('can_invite')->default(true);
            $table->boolean('can_moderate')->default(false);
            
            // Member activity tracking
            $table->timestamp('last_activity_at')->nullable();
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            
            // Notification settings
            $table->boolean('notify_new_posts')->default(true);
            $table->boolean('notify_new_members')->default(false);
            $table->boolean('notify_events')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Unique constraint - user can only have one membership per group
            $table->unique(['user_id', 'group_id'], 'unique_group_membership');
            
            // Performance indexes
            $table->index(['group_id', 'status', 'role']); // Group's members by status/role
            $table->index(['user_id', 'status', 'last_activity_at']); // User's active memberships
            $table->index(['group_id', 'status', 'approved_at']); // Members by join date
            $table->index(['status', 'requested_at']); // Pending membership requests
            $table->index(['role', 'group_id']); // Group moderators/admins
            
            // Composite indexes for complex queries
            $table->index(['group_id', 'status', 'role', 'last_activity_at']); // Active members by role
            $table->index(['user_id', 'status', 'role', 'approved_at']); // User's memberships timeline
            $table->index(['group_id', 'can_post', 'status']); // Members who can post
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_memberships');
    }
};
