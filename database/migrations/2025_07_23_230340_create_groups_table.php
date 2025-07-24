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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            
            // Group ownership and moderation
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');
            
            // Group basic info
            $table->string('name', 255);
            $table->text('description');
            $table->string('slug', 255)->unique(); // For SEO-friendly URLs
            $table->string('cover_image')->nullable();
            $table->string('icon')->nullable();
            
            // Group settings
            $table->enum('type', ['book_club', 'genre_discussion', 'author_fan', 'reading_challenge', 'general'])->default('general');
            $table->enum('privacy', ['public', 'closed', 'secret'])->default('public');
            $table->enum('join_policy', ['open', 'request', 'invite_only'])->default('open');
            
            // Group statistics (denormalized for performance)
            $table->unsignedInteger('members_count')->default(1); // Owner is automatically a member
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('pending_requests_count')->default(0);
            
            // Group activity
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('last_post_at')->nullable();
            
            // Group rules and moderation
            $table->text('rules')->nullable();
            $table->boolean('requires_admin_approval')->default(false);
            $table->boolean('allow_member_posts')->default(true);
            $table->boolean('allow_member_invites')->default(true);
            
            // Book-specific fields for book clubs
            $table->json('current_books')->nullable(); // Currently reading books
            $table->json('reading_schedule')->nullable(); // Schedule for book club readings
            $table->date('next_meeting_date')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['type', 'privacy', 'is_active']); // Browse groups by type
            $table->index(['privacy', 'is_active', 'members_count']); // Popular public groups
            $table->index(['owner_id', 'created_at']); // Groups owned by user
            $table->index(['is_featured', 'members_count']); // Featured groups
            $table->index(['last_activity_at', 'is_active']); // Recently active groups
            $table->index('slug'); // SEO URLs
            
            // Full-text search index for group discovery
            $table->fullText(['name', 'description'], 'groups_search');
            
            // Composite indexes for complex queries
            $table->index(['type', 'privacy', 'is_active', 'members_count']); // Browse with popularity
            $table->index(['privacy', 'is_active', 'last_activity_at']); // Recently active public groups
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
