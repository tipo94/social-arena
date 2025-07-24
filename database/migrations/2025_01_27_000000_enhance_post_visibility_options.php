<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update the enum to include close_friends
        DB::statement("ALTER TABLE posts MODIFY COLUMN visibility ENUM('public', 'friends', 'close_friends', 'friends_of_friends', 'private', 'group', 'custom') DEFAULT 'public'");
        
        Schema::table('posts', function (Blueprint $table) {
            // Add fields for enhanced visibility control
            $table->json('custom_audience')->nullable()->after('visibility')->comment('Custom audience list for custom visibility');
            $table->boolean('allow_resharing')->default(true)->after('custom_audience')->comment('Allow others to share this post');
            $table->boolean('allow_comments')->default(true)->after('allow_resharing')->comment('Allow comments on this post');
            $table->boolean('allow_reactions')->default(true)->after('allow_comments')->comment('Allow reactions on this post');
            $table->timestamp('visibility_expires_at')->nullable()->after('allow_reactions')->comment('When visibility expires (for temporary posts)');
            
            // Add visibility change tracking
            $table->json('visibility_history')->nullable()->after('visibility_expires_at')->comment('History of visibility changes');
            $table->timestamp('visibility_changed_at')->nullable()->after('visibility_history');
            
            // Add audience insights
            $table->unsignedInteger('views_count')->default(0)->after('shares_count');
            $table->unsignedInteger('reach_count')->default(0)->after('views_count')->comment('Unique users who saw this post');
            
            // Add indexes for new fields
            $table->index(['visibility', 'allow_resharing', 'published_at']); // Shareable posts
            $table->index(['visibility_expires_at']); // Temporary posts
            $table->index(['user_id', 'visibility', 'allow_comments']); // User's commentable posts
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Drop indexes
            $table->dropIndex(['visibility', 'allow_resharing', 'published_at']);
            $table->dropIndex(['visibility_expires_at']);
            $table->dropIndex(['user_id', 'visibility', 'allow_comments']);
            
            // Drop new columns
            $table->dropColumn([
                'custom_audience',
                'allow_resharing',
                'allow_comments',
                'allow_reactions',
                'visibility_expires_at',
                'visibility_history',
                'visibility_changed_at',
                'views_count',
                'reach_count',
            ]);
        });
        
        // Revert enum to original values
        DB::statement("ALTER TABLE posts MODIFY COLUMN visibility ENUM('public', 'friends', 'private', 'group') DEFAULT 'public'");
    }
}; 