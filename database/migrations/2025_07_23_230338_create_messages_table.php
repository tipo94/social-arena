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
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            
            // Message participants
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            
            // Threading support
            $table->string('thread_id', 100)->index(); // UUID for grouping related messages
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->onDelete('set null');
            
            // Message content
            $table->text('content');
            $table->enum('type', ['text', 'image', 'file', 'book_recommendation'])->default('text');
            $table->json('metadata')->nullable(); // For storing file info, book details, etc.
            
            // Message status tracking
            $table->timestamp('sent_at')->useCurrent();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->boolean('is_deleted_by_sender')->default(false);
            $table->boolean('is_deleted_by_receiver')->default(false);
            
            // Content moderation
            $table->boolean('is_reported')->default(false);
            $table->boolean('is_hidden')->default(false);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Performance indexes for messaging queries
            $table->index(['thread_id', 'sent_at']); // Message thread timeline
            $table->index(['sender_id', 'sent_at']); // Sent messages
            $table->index(['receiver_id', 'sent_at']); // Received messages
            $table->index(['receiver_id', 'read_at']); // Unread messages
            $table->index(['reply_to_id', 'sent_at']); // Message replies
            
            // Composite indexes for complex queries
            $table->index(['sender_id', 'receiver_id', 'sent_at'], 'messages_conversation_idx'); // Conversation between two users
            $table->index(['thread_id', 'is_deleted_by_sender', 'is_deleted_by_receiver'], 'messages_active_idx'); // Active messages in thread
            $table->index(['receiver_id', 'read_at', 'is_deleted_by_receiver'], 'messages_unread_idx'); // Unread non-deleted messages
            $table->index(['sender_id', 'receiver_id', 'thread_id'], 'messages_lookup_idx'); // Quick conversation lookup
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
