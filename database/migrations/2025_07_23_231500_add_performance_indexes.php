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
        // Additional performance indexes for social networking analytics
        
        // Posts table - Advanced feed algorithms
        Schema::table('posts', function (Blueprint $table) {
            // Trending posts index (engagement + recency)
            $table->index(['created_at', 'likes_count', 'comments_count'], 'posts_trending_idx');
            
            // User feed optimization (friends' posts)
            $table->index(['user_id', 'visibility', 'published_at', 'is_hidden'], 'posts_user_feed_idx');
            
            // Group activity tracking
            $table->index(['group_id', 'published_at', 'type'], 'posts_group_activity_idx');
        });
        
        // Comments table - Nested comment performance
        Schema::table('comments', function (Blueprint $table) {
            // Top-level comments first (for pagination)
            $table->index(['post_id', 'depth', 'likes_count', 'created_at'], 'comments_top_level_idx');
            
            // Comment tree traversal optimization
            $table->index(['path', 'depth', 'created_at'], 'comments_tree_idx');
        });
        
        // Friendships table - Social graph analytics
        Schema::table('friendships', function (Blueprint $table) {
            // Mutual friends calculation
            $table->index(['status', 'accepted_at', 'user_id'], 'friendships_mutual_idx');
            
            // Friend suggestions optimization
            $table->index(['friend_id', 'status', 'mutual_friends_count'], 'friendships_suggestions_idx');
        });
        
        // Messages table - Conversation optimization
        Schema::table('messages', function (Blueprint $table) {
            // Latest message per conversation
            $table->index(['thread_id', 'sent_at'], 'messages_thread_latest_idx');
            
            // Unread message counts
            $table->index(['receiver_id', 'read_at', 'thread_id'], 'messages_user_unread_idx');
        });
        
        // Notifications table - Real-time notification delivery
        Schema::table('notifications', function (Blueprint $table) {
            // Unread notification counts by type
            $table->index(['user_id', 'read_at', 'type', 'priority'], 'notifications_unread_type_idx');
            
            // Notification cleanup (old read notifications)
            $table->index(['read_at', 'created_at', 'is_dismissed'], 'notifications_cleanup_idx');
        });
        
        // User profiles table - User discovery
        Schema::table('user_profiles', function (Blueprint $table) {
            // User recommendations based on reading preferences
            $table->index(['is_active', 'allow_friend_requests', 'books_read_count'], 'profiles_recommendations_idx');
            
            // Local user discovery
            $table->index(['location', 'is_private_profile', 'last_profile_update'], 'profiles_local_idx');
        });
        
        // Groups table - Group discovery and activity
        Schema::table('groups', function (Blueprint $table) {
            // Active group discovery
            $table->index(['is_active', 'privacy', 'last_activity_at', 'members_count'], 'groups_discovery_idx');
            
            // Book club specific queries
            $table->index(['type', 'privacy', 'next_meeting_date'], 'groups_book_clubs_idx');
        });
        
        // Group memberships table - Member activity analysis
        Schema::table('group_memberships', function (Blueprint $table) {
            // Active member analysis
            $table->index(['group_id', 'status', 'last_activity_at', 'posts_count'], 'memberships_activity_idx');
            
            // Role-based permissions
            $table->index(['role', 'status', 'can_moderate'], 'memberships_moderation_idx');
        });
        
        // Likes table - Engagement analytics
        Schema::table('likes', function (Blueprint $table) {
            // Content popularity tracking
            $table->index(['likeable_type', 'type', 'created_at'], 'likes_popularity_idx');
            
            // User engagement patterns
            $table->index(['user_id', 'likeable_type', 'created_at'], 'likes_user_engagement_idx');
        });
        
        // Media attachments table - Content management
        Schema::table('media_attachments', function (Blueprint $table) {
            // Storage analytics and cleanup
            $table->index(['user_id', 'disk', 'size', 'created_at'], 'media_storage_idx');
            
            // Content moderation queue
            $table->index(['is_safe', 'status', 'type'], 'media_moderation_idx');
        });
        
        // Create materialized view for friend recommendations (MySQL compatible)
        if (DB::getDriverName() === 'mysql') {
            // Note: MySQL doesn't have materialized views, but we can create regular views
            // that can be refreshed manually for caching purposes
            DB::statement("
                CREATE OR REPLACE VIEW friend_suggestions AS
                SELECT 
                    u1.id as user_id,
                    u2.id as suggested_friend_id,
                    COUNT(DISTINCT f1.friend_id) as mutual_friends_count,
                    up2.friends_count,
                    up2.books_read_count
                FROM users u1
                CROSS JOIN users u2
                LEFT JOIN friendships f1 ON f1.user_id = u1.id AND f1.status = 'accepted'
                LEFT JOIN friendships f2 ON f2.user_id = f1.friend_id AND f2.friend_id = u2.id AND f2.status = 'accepted'
                LEFT JOIN friendships existing ON (existing.user_id = u1.id AND existing.friend_id = u2.id) 
                    OR (existing.user_id = u2.id AND existing.friend_id = u1.id)
                LEFT JOIN user_profiles up1 ON up1.user_id = u1.id
                LEFT JOIN user_profiles up2 ON up2.user_id = u2.id
                WHERE u1.id != u2.id
                    AND existing.id IS NULL
                    AND u1.is_active = 1
                    AND u2.is_active = 1
                    AND up1.allow_friend_requests = 1
                    AND up2.allow_friend_requests = 1
                    AND up2.is_private_profile = 0
                GROUP BY u1.id, u2.id, up2.friends_count, up2.books_read_count
                HAVING mutual_friends_count > 0
                ORDER BY mutual_friends_count DESC, up2.books_read_count DESC
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the view
        if (DB::getDriverName() === 'mysql') {
            DB::statement("DROP VIEW IF EXISTS friend_suggestions");
        }
        
        // Drop all the additional indexes
        Schema::table('posts', function (Blueprint $table) {
            $table->dropIndex('posts_trending_idx');
            $table->dropIndex('posts_user_feed_idx');
            $table->dropIndex('posts_group_activity_idx');
        });
        
        Schema::table('comments', function (Blueprint $table) {
            $table->dropIndex('comments_top_level_idx');
            $table->dropIndex('comments_tree_idx');
        });
        
        Schema::table('friendships', function (Blueprint $table) {
            $table->dropIndex('friendships_mutual_idx');
            $table->dropIndex('friendships_suggestions_idx');
        });
        
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_thread_latest_idx');
            $table->dropIndex('messages_user_unread_idx');
        });
        
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_unread_type_idx');
            $table->dropIndex('notifications_cleanup_idx');
        });
        
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropIndex('profiles_recommendations_idx');
            $table->dropIndex('profiles_local_idx');
        });
        
        Schema::table('groups', function (Blueprint $table) {
            $table->dropIndex('groups_discovery_idx');
            $table->dropIndex('groups_book_clubs_idx');
        });
        
        Schema::table('group_memberships', function (Blueprint $table) {
            $table->dropIndex('memberships_activity_idx');
            $table->dropIndex('memberships_moderation_idx');
        });
        
        Schema::table('likes', function (Blueprint $table) {
            $table->dropIndex('likes_popularity_idx');
            $table->dropIndex('likes_user_engagement_idx');
        });
        
        Schema::table('media_attachments', function (Blueprint $table) {
            $table->dropIndex('media_storage_idx');
            $table->dropIndex('media_moderation_idx');
        });
    }
}; 