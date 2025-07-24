<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bio',
        'location',
        'website',
        'birth_date',
        'gender',
        'avatar_url',
        'cover_image_url',
        'favorite_genres',
        'favorite_authors',
        'reading_goals',
        'reading_speed',
        'languages',
        
        // Basic privacy settings
        'is_private_profile',
        'show_reading_activity',
        'show_friends_list',
        'allow_friend_requests',
        'allow_group_invites',
        'allow_book_recommendations',
        
        // Enhanced privacy settings
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
        
        // Notification settings
        'email_notifications',
        'push_notifications',
        'notification_likes',
        'notification_comments',
        'notification_friend_requests',
        'notification_group_invites',
        'notification_book_recommendations',
        'notification_reading_reminders',
        
        // Counters and metadata
        'books_read_count',
        'reviews_written_count',
        'friends_count',
        'groups_count',
        'posts_count',
        'profile_completion_percentage',
        'is_verified',
        'verified_at',
        'is_active',
        'is_featured',
        'last_profile_update',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'birth_date' => 'date',
        'favorite_genres' => 'array',
        'favorite_authors' => 'array',
        'reading_goals' => 'array',
        'languages' => 'array',
        
        // Basic privacy settings
        'is_private_profile' => 'boolean',
        'show_reading_activity' => 'boolean',
        'show_friends_list' => 'boolean',
        'allow_friend_requests' => 'boolean',
        'allow_group_invites' => 'boolean',
        'allow_book_recommendations' => 'boolean',
        
        // Enhanced privacy settings (boolean fields)
        'show_online_status' => 'boolean',
        'show_last_activity' => 'boolean',
        'show_mutual_friends' => 'boolean',
        
        // Notification settings
        'email_notifications' => 'boolean',
        'push_notifications' => 'boolean',
        'notification_likes' => 'boolean',
        'notification_comments' => 'boolean',
        'notification_friend_requests' => 'boolean',
        'notification_group_invites' => 'boolean',
        'notification_book_recommendations' => 'boolean',
        'notification_reading_reminders' => 'boolean',
        
        // Status and metadata
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'last_profile_update' => 'datetime',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate and update profile completion percentage.
     */
    public function calculateCompletionPercentage(): int
    {
        $totalFields = 15;
        $completedFields = 0;

        // Basic info (already completed when profile is created)
        $completedFields += 2; // user_id and basic profile creation

        // Optional fields that contribute to completion
        if ($this->bio) $completedFields++;
        if ($this->location) $completedFields++;
        if ($this->website) $completedFields++;
        if ($this->birth_date) $completedFields++;
        if ($this->gender) $completedFields++;
        if ($this->avatar_url) $completedFields++;
        if ($this->cover_image_url) $completedFields++;
        if ($this->favorite_genres && count($this->favorite_genres) > 0) $completedFields++;
        if ($this->favorite_authors && count($this->favorite_authors) > 0) $completedFields++;
        if ($this->reading_goals && count($this->reading_goals) > 0) $completedFields++;
        if ($this->reading_speed) $completedFields++;
        if ($this->languages && count($this->languages) > 0) $completedFields++;

        $percentage = round(($completedFields / $totalFields) * 100);
        
        $this->update(['profile_completion_percentage' => $percentage]);
        
        return $percentage;
    }

    /**
     * Get reading preferences as a formatted array.
     */
    public function getReadingPreferences(): array
    {
        return [
            'genres' => $this->favorite_genres ?? [],
            'authors' => $this->favorite_authors ?? [],
            'goals' => $this->reading_goals ?? [],
            'speed' => $this->reading_speed,
            'languages' => $this->languages ?? [],
        ];
    }

    /**
     * Get privacy settings as an array.
     */
    public function getPrivacySettings(): array
    {
        return [
            'is_private_profile' => $this->is_private_profile,
            'show_reading_activity' => $this->show_reading_activity,
            'show_friends_list' => $this->show_friends_list,
            'allow_friend_requests' => $this->allow_friend_requests,
            'allow_group_invites' => $this->allow_group_invites,
            'allow_book_recommendations' => $this->allow_book_recommendations,
        ];
    }

    /**
     * Get notification preferences as an array.
     */
    public function getNotificationSettings(): array
    {
        return [
            'email_notifications' => $this->email_notifications,
            'push_notifications' => $this->push_notifications,
            'notification_likes' => $this->notification_likes,
            'notification_comments' => $this->notification_comments,
            'notification_friend_requests' => $this->notification_friend_requests,
            'notification_group_invites' => $this->notification_group_invites,
            'notification_book_recommendations' => $this->notification_book_recommendations,
            'notification_reading_reminders' => $this->notification_reading_reminders,
        ];
    }

    /**
     * Update notification settings.
     */
    public function updateNotificationSettings(array $settings): void
    {
        $allowedSettings = [
            'email_notifications',
            'push_notifications',
            'notification_likes',
            'notification_comments',
            'notification_friend_requests',
            'notification_group_invites',
            'notification_book_recommendations',
            'notification_reading_reminders',
        ];

        $validSettings = array_intersect_key($settings, array_flip($allowedSettings));
        $this->update($validSettings);
    }

    /**
     * Update privacy settings.
     */
    public function updatePrivacySettings(array $settings): void
    {
        $allowedSettings = [
            'is_private_profile',
            'show_reading_activity',
            'show_friends_list',
            'allow_friend_requests',
            'allow_group_invites',
            'allow_book_recommendations',
        ];

        $validSettings = array_intersect_key($settings, array_flip($allowedSettings));
        $this->update($validSettings);
    }

    /**
     * Increment activity counters.
     */
    public function incrementCounter(string $counter): void
    {
        $allowedCounters = [
            'books_read_count',
            'reviews_written_count',
            'friends_count',
            'groups_count',
            'posts_count',
        ];

        if (in_array($counter, $allowedCounters)) {
            $this->increment($counter);
        }
    }

    /**
     * Decrement activity counters.
     */
    public function decrementCounter(string $counter): void
    {
        $allowedCounters = [
            'books_read_count',
            'reviews_written_count',
            'friends_count',
            'groups_count',
            'posts_count',
        ];

        if (in_array($counter, $allowedCounters) && $this->{$counter} > 0) {
            $this->decrement($counter);
        }
    }

    /**
     * Mark profile as updated.
     */
    public function markAsUpdated(): void
    {
        $this->update(['last_profile_update' => now()]);
        $this->calculateCompletionPercentage();
    }
}
