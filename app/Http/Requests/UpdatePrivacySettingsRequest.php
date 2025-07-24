<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacySettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Profile Privacy Settings
            'profile_privacy' => ['sometimes', 'array'],
            'profile_privacy.is_private_profile' => ['sometimes', 'boolean'],
            'profile_privacy.profile_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'profile_privacy.contact_info_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'profile_privacy.location_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'profile_privacy.birth_date_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'profile_privacy.search_visibility' => ['sometimes', 'string', 'in:everyone,friends_of_friends,friends,nobody'],

            // Activity Privacy Settings
            'activity_privacy' => ['sometimes', 'array'],
            'activity_privacy.show_reading_activity' => ['sometimes', 'boolean'],
            'activity_privacy.show_online_status' => ['sometimes', 'boolean'],
            'activity_privacy.show_last_activity' => ['sometimes', 'boolean'],
            'activity_privacy.reading_activity_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'activity_privacy.post_visibility_default' => ['sometimes', 'string', 'in:public,friends,close_friends,private'],

            // Social Privacy Settings
            'social_privacy' => ['sometimes', 'array'],
            'social_privacy.show_friends_list' => ['sometimes', 'boolean'],
            'social_privacy.show_mutual_friends' => ['sometimes', 'boolean'],
            'social_privacy.friends_list_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'social_privacy.who_can_see_posts' => ['sometimes', 'string', 'in:public,friends,close_friends,private'],
            'social_privacy.who_can_tag_me' => ['sometimes', 'string', 'in:everyone,friends,friends_of_friends,nobody'],

            // Interaction Privacy Settings
            'interaction_privacy' => ['sometimes', 'array'],
            'interaction_privacy.allow_friend_requests' => ['sometimes', 'boolean'],
            'interaction_privacy.allow_group_invites' => ['sometimes', 'boolean'],
            'interaction_privacy.allow_book_recommendations' => ['sometimes', 'boolean'],
            'interaction_privacy.allow_messages_from' => ['sometimes', 'string', 'in:everyone,friends,friends_of_friends,nobody'],
            'interaction_privacy.friend_request_visibility' => ['sometimes', 'string', 'in:everyone,friends_of_friends,friends,nobody'],
            'interaction_privacy.who_can_find_me' => ['sometimes', 'string', 'in:everyone,friends_of_friends,friends,nobody'],

            // Content Privacy Settings
            'content_privacy' => ['sometimes', 'array'],
            'content_privacy.book_lists_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'content_privacy.reviews_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'content_privacy.reading_goals_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
            'content_privacy.reading_history_visibility' => ['sometimes', 'string', 'in:public,friends,friends_of_friends,private'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            '*.in' => 'The selected :attribute value is invalid.',
            '*.boolean' => 'The :attribute field must be true or false.',
            '*.array' => 'The :attribute must be a valid settings object.',
            'profile_privacy.profile_visibility.in' => 'Profile visibility must be one of: public, friends, friends_of_friends, or private.',
            'activity_privacy.reading_activity_visibility.in' => 'Reading activity visibility must be one of: public, friends, friends_of_friends, or private.',
            'social_privacy.who_can_tag_me.in' => 'Who can tag you must be one of: everyone, friends, friends_of_friends, or nobody.',
            'interaction_privacy.allow_messages_from.in' => 'Message permissions must be one of: everyone, friends, friends_of_friends, or nobody.',
            'content_privacy.*.in' => 'Content visibility must be one of: public, friends, friends_of_friends, or private.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'profile_privacy.is_private_profile' => 'private profile setting',
            'profile_privacy.profile_visibility' => 'profile visibility',
            'profile_privacy.contact_info_visibility' => 'contact information visibility',
            'profile_privacy.location_visibility' => 'location visibility',
            'profile_privacy.birth_date_visibility' => 'birth date visibility',
            'profile_privacy.search_visibility' => 'search visibility',
            'activity_privacy.show_reading_activity' => 'show reading activity',
            'activity_privacy.show_online_status' => 'show online status',
            'activity_privacy.show_last_activity' => 'show last activity',
            'activity_privacy.reading_activity_visibility' => 'reading activity visibility',
            'activity_privacy.post_visibility_default' => 'default post visibility',
            'social_privacy.show_friends_list' => 'show friends list',
            'social_privacy.show_mutual_friends' => 'show mutual friends',
            'social_privacy.friends_list_visibility' => 'friends list visibility',
            'social_privacy.who_can_see_posts' => 'post visibility',
            'social_privacy.who_can_tag_me' => 'tagging permissions',
            'interaction_privacy.allow_friend_requests' => 'allow friend requests',
            'interaction_privacy.allow_group_invites' => 'allow group invites',
            'interaction_privacy.allow_book_recommendations' => 'allow book recommendations',
            'interaction_privacy.allow_messages_from' => 'message permissions',
            'interaction_privacy.friend_request_visibility' => 'friend request visibility',
            'interaction_privacy.who_can_find_me' => 'search permissions',
            'content_privacy.book_lists_visibility' => 'book lists visibility',
            'content_privacy.reviews_visibility' => 'reviews visibility',
            'content_privacy.reading_goals_visibility' => 'reading goals visibility',
            'content_privacy.reading_history_visibility' => 'reading history visibility',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Ensure at least one privacy category is provided
            $data = $this->validated();
            $hasAnyCategory = collect(['profile_privacy', 'activity_privacy', 'social_privacy', 'interaction_privacy', 'content_privacy'])
                ->some(fn($category) => isset($data[$category]) && !empty($data[$category]));
            
            if (!$hasAnyCategory) {
                $validator->errors()->add('privacy_settings', 'At least one privacy setting must be provided.');
            }

            // Validate logical constraints
            $this->validatePrivacyLogic($validator);
        });
    }

    /**
     * Validate logical constraints for privacy settings
     */
    protected function validatePrivacyLogic($validator): void
    {
        $data = $this->all();
        
        // If profile is private, certain settings should be restrictive
        if (isset($data['profile_privacy']['is_private_profile']) && $data['profile_privacy']['is_private_profile']) {
            // Warn if search visibility is too open for a private profile
            if (isset($data['profile_privacy']['search_visibility']) && 
                in_array($data['profile_privacy']['search_visibility'], ['everyone', 'friends_of_friends'])) {
                $validator->errors()->add(
                    'profile_privacy.search_visibility',
                    'Search visibility should be limited when profile is set to private.'
                );
            }
        }

        // If friend requests are disabled, friend request visibility should be nobody
        if (isset($data['interaction_privacy']['allow_friend_requests']) && 
            !$data['interaction_privacy']['allow_friend_requests'] &&
            isset($data['interaction_privacy']['friend_request_visibility']) &&
            $data['interaction_privacy']['friend_request_visibility'] !== 'nobody') {
            
            $validator->errors()->add(
                'interaction_privacy.friend_request_visibility',
                'Friend request visibility should be set to "nobody" when friend requests are disabled.'
            );
        }
    }
} 