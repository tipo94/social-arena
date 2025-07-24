<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInterestsRequest extends FormRequest
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
        return [
            // Reading preferences
            'reading_preferences' => ['sometimes', 'array'],
            'reading_preferences.favorite_genres' => ['sometimes', 'array', 'max:10'],
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
            'reading_preferences.favorite_authors' => ['sometimes', 'array', 'max:20'],
            'reading_preferences.favorite_authors.*' => ['string', 'max:255'],
            'reading_preferences.preferred_book_length' => [
                'sometimes',
                'string',
                'in:short,medium,long,any'
            ],
            'reading_preferences.preferred_formats' => ['sometimes', 'array'],
            'reading_preferences.preferred_formats.*' => [
                'string',
                'in:physical,ebook,audiobook,graphic_novel'
            ],

            // Book club interests
            'book_club_interests' => ['sometimes', 'array'],
            'book_club_interests.interested_in_clubs' => ['sometimes', 'boolean'],
            'book_club_interests.preferred_meeting_frequency' => [
                'sometimes',
                'string',
                'in:weekly,biweekly,monthly,quarterly'
            ],
            'book_club_interests.preferred_meeting_type' => [
                'sometimes',
                'string',
                'in:in_person,virtual,hybrid'
            ],
            'book_club_interests.preferred_genres' => ['sometimes', 'array', 'max:5'],
            'book_club_interests.preferred_genres.*' => [
                'string',
                'in:fiction,non_fiction,mystery,romance,science_fiction,fantasy,thriller,biography,history,self_help,business,technology,health,cooking,travel,art,poetry,drama,philosophy,religion,science,politics,sports,humor,children'
            ],
            'book_club_interests.group_size_preference' => [
                'sometimes',
                'string',
                'in:small,medium,large,any'
            ],

            // Professional interests
            'professional_interests' => ['sometimes', 'array'],
            'professional_interests.industry' => ['sometimes', 'string', 'max:255'],
            'professional_interests.skills' => ['sometimes', 'array', 'max:20'],
            'professional_interests.skills.*' => ['string', 'max:100'],
            'professional_interests.interests' => ['sometimes', 'array', 'max:15'],
            'professional_interests.interests.*' => ['string', 'max:100'],
            'professional_interests.career_level' => [
                'sometimes',
                'string',
                'in:entry,mid,senior,executive,consultant,retired,student'
            ],
            'professional_interests.open_to_networking' => ['sometimes', 'boolean'],

            // General hobbies and interests
            'hobbies' => ['sometimes', 'array', 'max:20'],
            'hobbies.*' => ['string', 'max:100'],

            // Writing interests
            'writing_interests' => ['sometimes', 'array'],
            'writing_interests.is_writer' => ['sometimes', 'boolean'],
            'writing_interests.writing_genres' => ['sometimes', 'array', 'max:5'],
            'writing_interests.writing_genres.*' => [
                'string',
                'in:fiction,non_fiction,poetry,academic,technical,creative,journalism,blogging'
            ],
            'writing_interests.published_works' => ['sometimes', 'boolean'],
            'writing_interests.seeking_feedback' => ['sometimes', 'boolean'],

            // Learning goals
            'learning_goals' => ['sometimes', 'array'],
            'learning_goals.subjects' => ['sometimes', 'array', 'max:10'],
            'learning_goals.subjects.*' => ['string', 'max:100'],
            'learning_goals.skill_level' => [
                'sometimes',
                'string',
                'in:beginner,intermediate,advanced,expert'
            ],
            'learning_goals.time_commitment' => [
                'sometimes',
                'string',
                'in:casual,regular,intensive'
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'reading_preferences.favorite_genres.max' => 'You can select up to 10 favorite genres.',
            'reading_preferences.favorite_authors.max' => 'You can list up to 20 favorite authors.',
            'reading_preferences.books_per_year_goal.integer' => 'Reading goal must be a number.',
            'reading_preferences.books_per_year_goal.min' => 'Reading goal must be at least 1 book.',
            'reading_preferences.books_per_year_goal.max' => 'Reading goal cannot exceed 1000 books.',
            'book_club_interests.preferred_genres.max' => 'You can select up to 5 preferred book club genres.',
            'professional_interests.skills.max' => 'You can list up to 20 professional skills.',
            'professional_interests.interests.max' => 'You can list up to 15 professional interests.',
            'hobbies.max' => 'You can list up to 20 hobbies.',
            'hobbies.*.max' => 'Each hobby cannot exceed 100 characters.',
            'writing_interests.writing_genres.max' => 'You can select up to 5 writing genres.',
            'learning_goals.subjects.max' => 'You can list up to 10 learning subjects.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'reading_preferences' => 'reading preferences',
            'reading_preferences.favorite_genres' => 'favorite genres',
            'reading_preferences.reading_goals' => 'reading goals',
            'reading_preferences.books_per_year_goal' => 'yearly reading goal',
            'reading_preferences.favorite_authors' => 'favorite authors',
            'book_club_interests' => 'book club interests',
            'professional_interests' => 'professional interests',
            'hobbies' => 'hobbies',
            'writing_interests' => 'writing interests',
            'learning_goals' => 'learning goals',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Ensure at least one interest category is provided if any are provided
            $hasAnyInterests = collect([
                'reading_preferences',
                'book_club_interests', 
                'professional_interests',
                'hobbies',
                'writing_interests',
                'learning_goals'
            ])->some(fn($field) => $this->has($field) && !empty($this->input($field)));

            // Validate book club interest dependencies
            $bookClubInterests = $this->input('book_club_interests', []);
            if (!empty($bookClubInterests['interested_in_clubs']) && $bookClubInterests['interested_in_clubs']) {
                if (empty($bookClubInterests['preferred_meeting_frequency'])) {
                    $validator->errors()->add(
                        'book_club_interests.preferred_meeting_frequency',
                        'Please specify your preferred meeting frequency for book clubs.'
                    );
                }
            }

            // Validate writing interests dependencies  
            $writingInterests = $this->input('writing_interests', []);
            if (!empty($writingInterests['is_writer']) && $writingInterests['is_writer']) {
                if (empty($writingInterests['writing_genres'])) {
                    $validator->errors()->add(
                        'writing_interests.writing_genres',
                        'Please specify what genres you write if you are a writer.'
                    );
                }
            }
        });
    }
} 