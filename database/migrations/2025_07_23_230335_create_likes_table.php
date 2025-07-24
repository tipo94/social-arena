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
        Schema::create('likes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Polymorphic relationship - can like posts, comments, etc.
            $table->morphs('likeable'); // Creates likeable_id and likeable_type
            
            // Reaction type (for future expansion beyond just "like")
            $table->enum('type', ['like', 'love', 'laugh', 'angry', 'sad', 'wow'])->default('like');
            
            $table->timestamps();
            
            // Ensure a user can only like something once
            $table->unique(['user_id', 'likeable_id', 'likeable_type'], 'unique_user_like');
            
            // Performance indexes for social networking queries
            $table->index(['likeable_type', 'likeable_id', 'created_at']); // Likes for specific content
            $table->index(['user_id', 'created_at']); // User's likes timeline
            $table->index(['likeable_type', 'likeable_id', 'type']); // Reaction types
            $table->index(['user_id', 'likeable_type']); // User's likes by content type
            
            // Composite indexes for analytics
            $table->index(['likeable_type', 'type', 'created_at']); // Reaction analytics
            $table->index(['user_id', 'likeable_type', 'created_at']); // User activity by content type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('likes');
    }
};
