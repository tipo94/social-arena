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
        Schema::table('user_profiles', function (Blueprint $table) {
            // Enhanced profile visibility settings
            $table->string('profile_visibility')->default('public')->after('is_private_profile')->comment('public, friends, friends_of_friends, private');
            $table->string('contact_info_visibility')->default('friends')->after('profile_visibility')->comment('public, friends, friends_of_friends, private');
            $table->string('location_visibility')->default('friends')->after('contact_info_visibility')->comment('public, friends, friends_of_friends, private');
            $table->string('birth_date_visibility')->default('friends')->after('location_visibility')->comment('public, friends, friends_of_friends, private');
            $table->string('search_visibility')->default('everyone')->after('birth_date_visibility')->comment('everyone, friends_of_friends, friends, nobody');

            // Activity visibility settings
            $table->boolean('show_online_status')->default(true)->after('show_reading_activity');
            $table->boolean('show_last_activity')->default(true)->after('show_online_status');
            $table->string('reading_activity_visibility')->default('friends')->after('show_last_activity')->comment('public, friends, friends_of_friends, private');
            $table->string('post_visibility_default')->default('friends')->after('reading_activity_visibility')->comment('public, friends, close_friends, private');

            // Social interaction settings
            $table->boolean('show_mutual_friends')->default(true)->after('show_friends_list');
            $table->string('friends_list_visibility')->default('friends')->after('show_mutual_friends')->comment('public, friends, friends_of_friends, private');
            $table->string('who_can_see_posts')->default('friends')->after('friends_list_visibility')->comment('public, friends, close_friends, private');
            $table->string('who_can_tag_me')->default('friends')->after('who_can_see_posts')->comment('everyone, friends, friends_of_friends, nobody');

            // Interaction permissions
            $table->string('allow_messages_from')->default('friends')->after('allow_book_recommendations')->comment('everyone, friends, friends_of_friends, nobody');
            $table->string('friend_request_visibility')->default('everyone')->after('allow_messages_from')->comment('everyone, friends_of_friends, friends, nobody');
            $table->string('who_can_find_me')->default('everyone')->after('friend_request_visibility')->comment('everyone, friends_of_friends, friends, nobody');

            // Content visibility settings
            $table->string('book_lists_visibility')->default('friends')->after('who_can_find_me')->comment('public, friends, friends_of_friends, private');
            $table->string('reviews_visibility')->default('public')->after('book_lists_visibility')->comment('public, friends, friends_of_friends, private');
            $table->string('reading_goals_visibility')->default('friends')->after('reviews_visibility')->comment('public, friends, friends_of_friends, private');
            $table->string('reading_history_visibility')->default('friends')->after('reading_goals_visibility')->comment('public, friends, friends_of_friends, private');

            // Indexing for privacy queries
            $table->index(['profile_visibility']);
            $table->index(['search_visibility']);
            $table->index(['allow_friend_requests', 'friend_request_visibility']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['profile_visibility']);
            $table->dropIndex(['search_visibility']);
            $table->dropIndex(['allow_friend_requests', 'friend_request_visibility']);

            // Drop privacy fields
            $table->dropColumn([
                'profile_visibility',
                'contact_info_visibility',
                'location_visibility',
                'birth_date_visibility',
                'search_visibility',
                'show_online_status',
                'show_last_activity',
                'reading_activity_visibility',
                'post_visibility_default',
                'show_mutual_friends',
                'friends_list_visibility',
                'who_can_see_posts',
                'who_can_tag_me',
                'allow_messages_from',
                'friend_request_visibility',
                'who_can_find_me',
                'book_lists_visibility',
                'reviews_visibility',
                'reading_goals_visibility',
                'reading_history_visibility',
            ]);
        });
    }
}; 