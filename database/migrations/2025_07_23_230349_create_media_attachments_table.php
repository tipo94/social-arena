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
        Schema::create('media_attachments', function (Blueprint $table) {
            $table->id();
            
            // Owner of the media
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Polymorphic relationship - can attach to posts, messages, comments, etc.
            $table->morphs('attachable');
            
            // File information
            $table->string('filename', 500); // Original filename
            $table->string('disk', 50)->default('local'); // Storage disk (local, s3, etc.)
            $table->string('path', 1000); // File path on disk
            $table->string('url', 1000)->nullable(); // Public URL (for CDN)
            
            // File metadata
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size'); // File size in bytes
            $table->string('extension', 10);
            $table->string('alt_text', 500)->nullable(); // For accessibility
            
            // Media type classification
            $table->enum('type', ['image', 'video', 'audio', 'document', 'archive', 'other'])->default('other');
            
            // Image/Video specific metadata
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration')->nullable(); // For video/audio in seconds
            
            // Processing status
            $table->enum('status', ['uploading', 'processing', 'ready', 'failed'])->default('uploading');
            $table->json('variants')->nullable(); // Different sizes/formats (thumbnails, compressed versions)
            
            // Content analysis (for moderation)
            $table->json('analysis_results')->nullable(); // AI moderation results
            $table->boolean('is_safe')->default(true); // Content safety flag
            $table->boolean('is_public')->default(true); // Public accessibility
            
            // Usage tracking
            $table->unsignedInteger('downloads_count')->default(0);
            $table->unsignedInteger('views_count')->default(0);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes
            $table->index(['user_id', 'created_at']); // User's media timeline
            $table->index(['type', 'created_at']); // Media by type
            $table->index(['status', 'created_at']); // Processing status
            $table->index(['mime_type', 'size']); // File type and size queries
            
            // Composite indexes for complex queries
            $table->index(['user_id', 'type', 'created_at']); // User's media by type
            $table->index(['attachable_type', 'type', 'is_public']); // Public media by content type
            $table->index(['status', 'is_safe', 'created_at']); // Safe, processed media
            $table->index(['user_id', 'status', 'type']); // User's media by status and type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_attachments');
    }
};
