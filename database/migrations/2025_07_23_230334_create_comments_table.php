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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade');
            
            // Content
            $table->text('content');
            $table->enum('type', ['text', 'image', 'gif'])->default('text');
            
            // Engagement metrics (denormalized for performance)
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('replies_count')->default(0);
            
            // Nested comments support
            $table->unsignedInteger('depth')->default(0); // 0 = root comment, 1 = reply, 2 = reply to reply, etc.
            $table->string('path', 500)->nullable(); // Materialized path for efficient tree queries (e.g., "1.5.23")
            
            // Content moderation
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['post_id', 'created_at']); // Post's comments timeline
            $table->index(['user_id', 'created_at']); // User's comments
            $table->index(['parent_id', 'created_at']); // Comment replies
            $table->index(['path', 'created_at']); // Nested comment trees
            $table->index(['depth', 'post_id']); // Comments by depth level
            $table->index(['likes_count', 'created_at']); // Popular comments
            $table->index(['is_reported', 'moderated_at']); // Moderation queue
            
            // Composite indexes for complex queries
            $table->index(['post_id', 'parent_id', 'created_at']); // Root comments first
            $table->index(['post_id', 'is_hidden', 'created_at']); // Visible comments
            $table->index(['user_id', 'post_id', 'created_at']); // User's comments on specific posts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
