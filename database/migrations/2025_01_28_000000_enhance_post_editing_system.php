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
        Schema::table('posts', function (Blueprint $table) {
            // Edit tracking fields
            $table->json('edit_history')->nullable()->after('visibility_changed_at')->comment('Complete edit history with diffs');
            $table->timestamp('last_edited_at')->nullable()->after('edit_history')->comment('When post was last edited');
            $table->foreignId('last_edited_by')->nullable()->after('last_edited_at')->constrained('users')->onDelete('set null');
            $table->unsignedTinyInteger('edit_count')->default(0)->after('last_edited_by')->comment('Number of times edited');
            $table->boolean('is_edited')->default(false)->after('edit_count')->comment('Flag to show post has been edited');
            
            // Time-based edit restrictions
            $table->boolean('allow_editing')->default(true)->after('is_edited')->comment('Whether editing is allowed');
            $table->timestamp('editing_locked_at')->nullable()->after('allow_editing')->comment('When editing was locked');
            $table->timestamp('edit_deadline')->nullable()->after('editing_locked_at')->comment('Deadline for editing (24h default)');
            
            // Enhanced deletion tracking
            $table->string('deletion_reason', 500)->nullable()->after('deleted_at')->comment('Reason for deletion');
            $table->foreignId('deleted_by')->nullable()->after('deletion_reason')->constrained('users')->onDelete('set null');
            $table->timestamp('deletion_scheduled_at')->nullable()->after('deleted_by')->comment('When deletion was scheduled');
            $table->timestamp('permanent_deletion_at')->nullable()->after('deletion_scheduled_at')->comment('When permanent deletion will occur');
            $table->boolean('can_be_restored')->default(true)->after('permanent_deletion_at')->comment('Whether post can be restored');
            
            // Content versioning
            $table->unsignedTinyInteger('current_version')->default(1)->after('can_be_restored')->comment('Current content version');
            $table->json('original_content')->nullable()->after('current_version')->comment('Original content backup');
            
            // Notification tracking
            $table->boolean('edit_notifications_sent')->default(false)->after('original_content')->comment('Whether edit notifications were sent');
            $table->json('notification_recipients')->nullable()->after('edit_notifications_sent')->comment('Users notified of edits');
            
            // Indexes for performance
            $table->index(['is_edited', 'last_edited_at']); // Find recently edited posts
            $table->index(['editing_locked_at']); // Find locked posts
            $table->index(['edit_deadline']); // Posts with edit deadlines
            $table->index(['deletion_scheduled_at']); // Scheduled deletions
            $table->index(['permanent_deletion_at']); // Permanent deletion queue
            $table->index(['deleted_by', 'deleted_at']); // Admin deletion tracking
        });

        // Create post revisions table for detailed version tracking
        Schema::create('post_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade')->comment('User who made the edit');
            $table->unsignedTinyInteger('version_number')->comment('Version number of this revision');
            
            // Content snapshot
            $table->text('content')->nullable()->comment('Content at this revision');
            $table->string('type', 50)->comment('Post type at this revision');
            $table->json('metadata')->nullable()->comment('Metadata at this revision');
            $table->string('visibility', 50)->comment('Visibility at this revision');
            $table->json('media_attachments')->nullable()->comment('Media attachments at this revision');
            
            // Change tracking
            $table->json('changes_made')->comment('Specific fields that were changed');
            $table->json('diff_data')->nullable()->comment('Detailed diff information');
            $table->string('edit_reason', 500)->nullable()->comment('Reason for the edit');
            $table->string('edit_source', 50)->default('web')->comment('Source of edit: web, api, admin');
            
            // Technical metadata
            $table->string('user_agent', 500)->nullable()->comment('User agent of editor');
            $table->ipAddress('ip_address')->nullable()->comment('IP address of editor');
            $table->boolean('is_major_edit')->default(false)->comment('Whether this is considered a major edit');
            $table->unsignedInteger('content_length')->default(0)->comment('Length of content');
            $table->unsignedInteger('characters_added')->default(0)->comment('Characters added in this edit');
            $table->unsignedInteger('characters_removed')->default(0)->comment('Characters removed in this edit');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['post_id', 'version_number']); // Find specific revision
            $table->index(['post_id', 'created_at']); // Revision history
            $table->index(['user_id', 'created_at']); // User's edit history
            $table->index(['is_major_edit', 'created_at']); // Major edits
            $table->index(['edit_source']); // Edits by source
            
            // Composite indexes
            $table->index(['post_id', 'user_id', 'created_at']); // Post edits by user
            $table->unique(['post_id', 'version_number']); // Ensure version uniqueness
        });

        // Create post deletion logs table
        Schema::create('post_deletion_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->comment('ID of deleted post');
            $table->foreignId('post_user_id')->constrained('users', 'id')->onDelete('cascade')->comment('Original post owner');
            $table->foreignId('deleted_by')->constrained('users')->onDelete('cascade')->comment('User who deleted the post');
            
            // Deletion details
            $table->string('deletion_type', 50)->comment('soft, permanent, admin, auto');
            $table->string('deletion_reason', 500)->nullable()->comment('Reason for deletion');
            $table->json('post_snapshot')->comment('Complete post data at time of deletion');
            $table->timestamp('original_created_at')->comment('When post was originally created');
            $table->timestamp('deleted_at')->comment('When post was deleted');
            $table->timestamp('restoration_deadline')->nullable()->comment('Deadline for restoration');
            
            // Recovery tracking
            $table->boolean('was_restored')->default(false)->comment('Whether post was restored');
            $table->timestamp('restored_at')->nullable()->comment('When post was restored');
            $table->foreignId('restored_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('restoration_reason', 500)->nullable()->comment('Reason for restoration');
            
            // Administrative tracking
            $table->ipAddress('deletion_ip')->nullable()->comment('IP address of deletion');
            $table->string('user_agent', 500)->nullable()->comment('User agent for deletion');
            $table->boolean('is_admin_action')->default(false)->comment('Whether deletion was administrative');
            $table->json('moderator_notes')->nullable()->comment('Notes from moderators');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['post_user_id', 'deleted_at']); // User's deleted posts
            $table->index(['deleted_by', 'deleted_at']); // Deletions by user
            $table->index(['deletion_type', 'deleted_at']); // Deletions by type
            $table->index(['was_restored', 'restored_at']); // Restoration tracking
            $table->index(['restoration_deadline']); // Posts eligible for restoration
            $table->index(['is_admin_action', 'deleted_at']); // Admin actions
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_deletion_logs');
        Schema::dropIfExists('post_revisions');
        
        Schema::table('posts', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['is_edited', 'last_edited_at']);
            $table->dropIndex(['editing_locked_at']);
            $table->dropIndex(['edit_deadline']);
            $table->dropIndex(['deletion_scheduled_at']);
            $table->dropIndex(['permanent_deletion_at']);
            $table->dropIndex(['deleted_by', 'deleted_at']);
            
            // Drop columns
            $table->dropColumn([
                'edit_history',
                'last_edited_at',
                'last_edited_by',
                'edit_count',
                'is_edited',
                'allow_editing',
                'editing_locked_at',
                'edit_deadline',
                'deletion_reason',
                'deleted_by',
                'deletion_scheduled_at',
                'permanent_deletion_at',
                'can_be_restored',
                'current_version',
                'original_content',
                'edit_notifications_sent',
                'notification_recipients',
            ]);
        });
    }
}; 