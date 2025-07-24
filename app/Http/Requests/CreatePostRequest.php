<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class CreatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'content' => 'required_without:media_ids|string|max:5000',
            'type' => 'sometimes|string|in:text,image,video,link,book_review,poll',
            'visibility' => 'sometimes|string|in:public,friends,close_friends,friends_of_friends,private,group,custom',
            'group_id' => [
                'sometimes',
                'integer',
                'exists:groups,id',
                function ($attribute, $value, $fail) {
                    if ($value && $this->input('visibility') !== 'group') {
                        $fail('Posts with group_id must have group visibility.');
                    }
                }
            ],
            'metadata' => 'sometimes|array',
            'metadata.book_title' => 'sometimes|string|max:255',
            'metadata.book_author' => 'sometimes|string|max:255',
            'metadata.book_isbn' => 'sometimes|string|max:20',
            'metadata.book_rating' => 'sometimes|numeric|min:1|max:5',
            'metadata.book_review' => 'sometimes|string|max:2000',
            'metadata.link_url' => 'sometimes|url|max:500',
            'metadata.link_title' => 'sometimes|string|max:255',
            'metadata.link_description' => 'sometimes|string|max:500',
            'metadata.poll_question' => 'sometimes|string|max:255',
            'metadata.poll_options' => 'sometimes|array|min:2|max:10',
            'metadata.poll_options.*' => 'string|max:100',
            'metadata.poll_expires_at' => 'sometimes|date|after:now',
            'media_ids' => 'sometimes|array|max:10',
            'media_ids.*' => 'integer|exists:media_attachments,id',
            'scheduled_at' => 'sometimes|date|after:now|before:' . now()->addYear(),
            'tags' => 'sometimes|array|max:20',
            'tags.*' => 'string|max:50|regex:/^[a-zA-Z0-9_]+$/',
            'custom_audience' => 'sometimes|array|max:100',
            'custom_audience.*' => 'integer|exists:users,id',
            'allow_resharing' => 'sometimes|boolean',
            'allow_comments' => 'sometimes|boolean',
            'allow_reactions' => 'sometimes|boolean',
            'visibility_expires_at' => 'sometimes|date|after:now|before:' . now()->addMonths(3),
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'content.required_without' => 'Post content is required when no media is attached.',
            'content.max' => 'Post content cannot exceed 5000 characters.',
            'type.in' => 'Invalid post type.',
            'visibility.in' => 'Invalid visibility setting.',
            'group_id.exists' => 'The selected group does not exist.',
            'metadata.book_rating.min' => 'Book rating must be at least 1 star.',
            'metadata.book_rating.max' => 'Book rating cannot exceed 5 stars.',
            'metadata.link_url.url' => 'Please provide a valid URL.',
            'metadata.poll_options.min' => 'Poll must have at least 2 options.',
            'metadata.poll_options.max' => 'Poll cannot have more than 10 options.',
            'metadata.poll_expires_at.after' => 'Poll expiration must be in the future.',
            'media_ids.max' => 'Cannot attach more than 10 media files.',
            'media_ids.*.exists' => 'One or more media files do not exist.',
            'scheduled_at.after' => 'Scheduled time must be in the future.',
            'scheduled_at.before' => 'Cannot schedule posts more than one year in advance.',
            'tags.max' => 'Cannot use more than 20 tags.',
            'tags.*.regex' => 'Tags can only contain letters, numbers, and underscores.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that user has access to the group if group_id is provided
            if ($this->has('group_id')) {
                $this->validateGroupAccess($validator);
            }

            // Validate media ownership
            if ($this->has('media_ids')) {
                $this->validateMediaOwnership($validator);
            }

            // Validate post type specific requirements
            $this->validateTypeSpecificRequirements($validator);

            // Validate content length and format
            if ($this->has('content')) {
                $this->validateContentFormat($validator);
            }
        });
    }

    /**
     * Validate that user has access to the specified group.
     */
    protected function validateGroupAccess($validator): void
    {
        $groupId = $this->input('group_id');
        $user = Auth::user();

        // Check if user is a member of the group
        $isMember = \App\Models\GroupMembership::where('group_id', $groupId)
                                             ->where('user_id', $user->id)
                                             ->where('status', 'approved')
                                             ->exists();

        if (!$isMember) {
            $validator->errors()->add('group_id', 'You are not a member of this group.');
        }

        // Check if group allows member posts
        $group = \App\Models\Group::find($groupId);
        if ($group && !$group->allow_member_posts && $group->owner_id !== $user->id) {
            $validator->errors()->add('group_id', 'This group does not allow member posts.');
        }
    }

    /**
     * Validate that user owns the media attachments.
     */
    protected function validateMediaOwnership($validator): void
    {
        $mediaIds = $this->input('media_ids', []);
        $user = Auth::user();

        $userMediaCount = \App\Models\MediaAttachment::whereIn('id', $mediaIds)
                                                    ->where('user_id', $user->id)
                                                    ->whereNull('attachable_id')
                                                    ->count();

        if ($userMediaCount !== count($mediaIds)) {
            $validator->errors()->add('media_ids', 'You can only attach media that you have uploaded.');
        }
    }

    /**
     * Validate type-specific requirements.
     */
    protected function validateTypeSpecificRequirements($validator): void
    {
        $type = $this->input('type', 'text');

        switch ($type) {
            case 'book_review':
                if (!$this->has('metadata.book_title')) {
                    $validator->errors()->add('metadata.book_title', 'Book title is required for book reviews.');
                }
                break;

            case 'link':
                if (!$this->has('metadata.link_url')) {
                    $validator->errors()->add('metadata.link_url', 'URL is required for link posts.');
                }
                break;

            case 'poll':
                if (!$this->has('metadata.poll_question')) {
                    $validator->errors()->add('metadata.poll_question', 'Poll question is required.');
                }
                if (!$this->has('metadata.poll_options') || count($this->input('metadata.poll_options', [])) < 2) {
                    $validator->errors()->add('metadata.poll_options', 'Poll must have at least 2 options.');
                }
                break;

            case 'image':
            case 'video':
                if (!$this->has('media_ids') || empty($this->input('media_ids'))) {
                    $validator->errors()->add('media_ids', "Media is required for {$type} posts.");
                }
                break;
        }
    }

    /**
     * Validate content format and length.
     */
    protected function validateContentFormat($validator): void
    {
        $content = $this->input('content');
        
        if (!$content) {
            return;
        }

        $textFormattingService = app(\App\Services\TextFormattingService::class);
        $errors = $textFormattingService->validate($content);

        foreach ($errors as $error) {
            $validator->errors()->add('content', $error);
        }
    }

    /**
     * Get the validated data from the request with processed content.
     */
    public function validatedWithProcessing(): array
    {
        $validated = $this->validated();

        // Set default type if not provided
        if (!isset($validated['type'])) {
            if (isset($validated['media_ids']) && !empty($validated['media_ids'])) {
                // Determine type based on first media attachment
                $firstMedia = \App\Models\MediaAttachment::find($validated['media_ids'][0]);
                $validated['type'] = $firstMedia?->type === 'image' ? 'image' : 'video';
            } else {
                $validated['type'] = 'text';
            }
        }

        return $validated;
    }
} 