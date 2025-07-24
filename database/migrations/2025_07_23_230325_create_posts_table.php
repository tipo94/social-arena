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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('group_id')->nullable();
            
            // Content fields
            $table->text('content');
            $table->string('type', 50)->default('text'); // text, image, video, link, book_review
            $table->json('metadata')->nullable(); // For storing additional data like book info, link previews
            
            // Visibility settings
            $table->enum('visibility', ['public', 'friends', 'private', 'group'])->default('public');
            
            // Engagement metrics (denormalized for performance)
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);
            $table->unsignedInteger('shares_count')->default(0);
            
            // Content moderation
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            
            // Scheduling
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_scheduled')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes for social networking queries
            $table->index(['user_id', 'created_at']); // User's posts timeline
            $table->index(['visibility', 'published_at', 'is_hidden']); // Public feed
            $table->index(['group_id', 'created_at']); // Group posts
            $table->index(['type', 'created_at']); // Filter by content type
            $table->index(['likes_count', 'created_at']); // Popular posts
            $table->index(['is_reported', 'moderated_at']); // Moderation queue
            $table->index('published_at'); // Scheduled posts
            
            // Composite indexes for complex queries
            $table->index(['user_id', 'visibility', 'published_at']); // User's visible posts
            $table->index(['visibility', 'is_hidden', 'published_at', 'likes_count']); // Feed with popularity
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
