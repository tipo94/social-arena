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
        Schema::create('shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('User who shared the content');
            
            // Polymorphic relationship for shareable content
            $table->unsignedBigInteger('shareable_id');
            $table->string('shareable_type', 100); // Post, Comment, etc.
            $table->index(['shareable_id', 'shareable_type'], 'shares_shareable_index');
            
            // Share type and platform
            $table->enum('share_type', [
                'repost',           // Internal repost without additional content
                'quote_repost',     // Internal repost with user's commentary
                'external',         // External platform sharing
                'link_share',       // Direct link sharing
                'private_share',    // Share to specific users
                'internal'          // Generic internal sharing
            ])->default('repost');
            
            $table->string('platform', 50)->nullable()->comment('External platform: twitter, facebook, linkedin, etc.');
            
            // Share content (for quote reposts)
            $table->text('content')->nullable()->comment('Additional content when quote sharing');
            $table->json('metadata')->nullable()->comment('Additional metadata, tracking info, etc.');
            
            // Sharing target (for private/directed shares)
            $table->foreignId('shared_to_user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->unsignedBigInteger('shared_to_group_id')->nullable(); // Will add constraint after groups table is created
            
            // Share settings
            $table->enum('visibility', ['public', 'friends', 'private'])->default('public');
            $table->boolean('is_quote_share')->default(false)->comment('Whether this share includes user commentary');
            $table->boolean('is_private_share')->default(false)->comment('Whether this is a private share to specific users');
            
            // Timestamps
            $table->timestamp('shared_at')->default(now())->comment('When the content was shared');
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['user_id', 'shared_at'], 'shares_user_timeline');
            $table->index(['share_type', 'shared_at'], 'shares_type_timeline');
            $table->index(['platform', 'shared_at'], 'shares_platform_timeline');
            $table->index(['visibility', 'shared_at'], 'shares_visibility_timeline');
            $table->index(['is_quote_share', 'shared_at'], 'shares_quote_timeline');
            
            // Compound indexes for common queries
            $table->index(['shareable_type', 'shareable_id', 'share_type'], 'shares_content_type');
            $table->index(['user_id', 'shareable_type', 'shareable_id'], 'shares_user_content');
            
            // Unique constraint to prevent duplicate shares (same user sharing same content)
            $table->unique(['user_id', 'shareable_id', 'shareable_type', 'share_type'], 'shares_unique_user_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shares');
    }
}; 