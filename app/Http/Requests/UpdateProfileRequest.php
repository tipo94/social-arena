<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            // User table fields
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('users', 'username')->ignore($userId)
            ],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone'],
            'locale' => [
                'sometimes',
                'string',
                'max:10',
                'in:en,es,fr,de,it,pt,ja,ko,zh,ru,ar'
            ],
            'theme' => [
                'sometimes',
                'string',
                'in:light,dark,auto'
            ],

            // Profile table fields
            'bio' => ['sometimes', 'string', 'max:1000'],
            'location' => ['sometimes', 'string', 'max:255'],
            'website' => ['sometimes', 'url', 'max:255'],
            'birth_date' => ['sometimes', 'date', 'before:today', 'after:1900-01-01'],
            'gender' => [
                'sometimes',
                'string',
                'in:male,female,non_binary,prefer_not_to_say,other'
            ],
            'occupation' => ['sometimes', 'string', 'max:255'],
            'education' => ['sometimes', 'string', 'max:255'],
            'phone' => [
                'sometimes',
                'string',
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/' // International phone format
            ],

            // Social links
            'social_links' => ['sometimes', 'array'],
            'social_links.twitter' => ['sometimes', 'url', 'max:255'],
            'social_links.instagram' => ['sometimes', 'url', 'max:255'],
            'social_links.linkedin' => ['sometimes', 'url', 'max:255'],
            'social_links.facebook' => ['sometimes', 'url', 'max:255'],
            'social_links.youtube' => ['sometimes', 'url', 'max:255'],
            'social_links.tiktok' => ['sometimes', 'url', 'max:255'],
            'social_links.goodreads' => ['sometimes', 'url', 'max:255'],

            // Reading preferences
            'reading_preferences' => ['sometimes', 'array'],
            'reading_preferences.favorite_genres' => ['sometimes', 'array'],
            'reading_preferences.favorite_genres.*' => [
                'string',
                'in:fiction,non_fiction,mystery,romance,science_fiction,fantasy,thriller,biography,history,self_help,business,technology,health,cooking,travel,art,poetry,drama,philosophy,religion,science,politics,sports,humor,children'
            ],
            'reading_preferences.reading_goals' => [
                'sometimes',
                'string',
                'in:casual_reader,book_per_month,book_per_week,speed_reader,quality_over_quantity,genre_explorer,author_completionist,award_winners_only,classics_focus,new_releases_focus'
            ],
            'reading_preferences.books_per_year_goal' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'reading_preferences.favorite_authors' => ['sometimes', 'array'],
            'reading_preferences.favorite_authors.*' => ['string', 'max:255'],

            // Book club and community interests
            'book_club_interests' => ['sometimes', 'array'],
            'book_club_interests.interested_in_clubs' => ['sometimes', 'boolean'],
            'book_club_interests.preferred_meeting_frequency' => [
                'sometimes',
                'string',
                'in:weekly,biweekly,monthly,quarterly'
            ],
            'book_club_interests.preferred_genres' => ['sometimes', 'array'],
            'book_club_interests.preferred_genres.*' => [
                'string',
                'in:fiction,non_fiction,mystery,romance,science_fiction,fantasy,thriller,biography,history,self_help,business,technology,health,cooking,travel,art,poetry,drama,philosophy,religion,science,politics,sports,humor,children'
            ],

            // Professional interests
            'professional_interests' => ['sometimes', 'array'],
            'professional_interests.industry' => ['sometimes', 'string', 'max:255'],
            'professional_interests.skills' => ['sometimes', 'array'],
            'professional_interests.skills.*' => ['string', 'max:100'],
            'professional_interests.interests' => ['sometimes', 'array'],
            'professional_interests.interests.*' => ['string', 'max:100'],

            // General hobbies
            'hobbies' => ['sometimes', 'array'],
            'hobbies.*' => ['string', 'max:100'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'name.min' => 'Full name must be at least 2 characters.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'username.min' => 'Username must be at least 3 characters.',
            'bio.max' => 'Bio cannot exceed 1000 characters.',
            'location.max' => 'Location cannot exceed 255 characters.',
            'website.url' => 'Website must be a valid URL.',
            'birth_date.before' => 'Birth date must be before today.',
            'birth_date.after' => 'Birth date must be after 1900.',
            'phone.regex' => 'Please provide a valid phone number.',
            'timezone.timezone' => 'Please provide a valid timezone.',
            'locale.in' => 'Please select a valid language.',
            'theme.in' => 'Please select a valid theme.',
            'gender.in' => 'Please select a valid gender option.',
            'social_links.*.url' => 'Social media links must be valid URLs.',
            'reading_preferences.favorite_genres.*.in' => 'Please select valid genres.',
            'reading_preferences.books_per_year_goal.integer' => 'Reading goal must be a number.',
            'reading_preferences.books_per_year_goal.min' => 'Reading goal must be at least 1.',
            'reading_preferences.books_per_year_goal.max' => 'Reading goal cannot exceed 1000.',
            'hobbies.*.max' => 'Each hobby cannot exceed 100 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'username' => 'username',
            'bio' => 'biography',
            'location' => 'location',
            'website' => 'website',
            'birth_date' => 'date of birth',
            'gender' => 'gender',
            'occupation' => 'occupation',
            'education' => 'education',
            'phone' => 'phone number',
            'timezone' => 'timezone',
            'locale' => 'language',
            'theme' => 'theme',
            'social_links' => 'social media links',
            'reading_preferences' => 'reading preferences',
            'book_club_interests' => 'book club interests',
            'professional_interests' => 'professional interests',
            'hobbies' => 'hobbies',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate age if birth_date is provided
            if ($this->birth_date) {
                $age = \Carbon\Carbon::parse($this->birth_date)->age;
                if ($age < 13) {
                    $validator->errors()->add('birth_date', 'You must be at least 13 years old to use this platform.');
                }
            }

            // Validate social links domain restrictions
            $socialLinks = $this->social_links ?? [];
            foreach ($socialLinks as $platform => $url) {
                if ($url && !$this->isValidSocialPlatformUrl($platform, $url)) {
                    $validator->errors()->add(
                        "social_links.{$platform}",
                        "Please provide a valid {$platform} URL."
                    );
                }
            }
        });
    }

    /**
     * Validate social platform URLs
     */
    protected function isValidSocialPlatformUrl(string $platform, string $url): bool
    {
        $platformDomains = [
            'twitter' => ['twitter.com', 'x.com'],
            'instagram' => ['instagram.com'],
            'linkedin' => ['linkedin.com'],
            'facebook' => ['facebook.com', 'fb.com'],
            'youtube' => ['youtube.com', 'youtu.be'],
            'tiktok' => ['tiktok.com'],
            'goodreads' => ['goodreads.com'],
        ];

        if (!isset($platformDomains[$platform])) {
            return true; // Allow unknown platforms
        }

        $parsedUrl = parse_url($url);
        if (!$parsedUrl || !isset($parsedUrl['host'])) {
            return false;
        }

        $host = strtolower($parsedUrl['host']);
        $allowedDomains = $platformDomains[$platform];

        foreach ($allowedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
} 