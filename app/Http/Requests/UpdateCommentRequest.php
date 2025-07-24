<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateCommentRequest extends FormRequest
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
            'content' => [
                'sometimes',
                'required',
                'string',
                'min:1',
                'max:2000',
                'regex:/\S/', // Must contain at least one non-whitespace character
            ],
            'type' => [
                'sometimes',
                'string',
                'in:text,image,gif',
            ],
        ];
    }

    /**
     * Get custom messages for validation errors.
     */
    public function messages(): array
    {
        return [
            'content.required' => 'Comment content is required.',
            'content.min' => 'Comment must not be empty.',
            'content.max' => 'Comment cannot exceed 2000 characters.',
            'content.regex' => 'Comment must contain at least some text.',
            'type.in' => 'Invalid comment type. Must be text, image, or gif.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from content if provided
        if ($this->has('content')) {
            $this->merge([
                'content' => trim($this->input('content')),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation for content based on type
            $type = $this->input('type');
            $content = $this->input('content');

            if ($type === 'text' && $content) {
                // Text comments should have meaningful content
                if (strlen(trim(strip_tags($content))) < 1) {
                    $validator->errors()->add('content', 'Text comments must contain at least one character.');
                }
            }

            // Check for spam patterns (basic implementation)
            if ($content && $this->containsSpamPatterns($content)) {
                $validator->errors()->add('content', 'Comment appears to contain spam or inappropriate content.');
            }

            // Ensure at least one field is being updated
            if (!$this->has('content') && !$this->has('type')) {
                $validator->errors()->add('general', 'At least one field must be provided for update.');
            }
        });
    }

    /**
     * Basic spam detection patterns.
     */
    private function containsSpamPatterns(string $content): bool
    {
        $spamPatterns = [
            '/\b(viagra|cialis|casino|lottery|winner)\b/i',
            '/\b(click here|buy now|limited time)\b/i',
            '/https?:\/\/[^\s]+/i', // No direct links allowed in comments
            '/(.)\1{10,}/', // Repeated characters
        ];

        foreach ($spamPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }
} 