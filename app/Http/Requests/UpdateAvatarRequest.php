<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
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
            'avatar' => [
                'required',
                'image',
                'max:2048', // 2MB max
                'mimes:jpeg,png,jpg,gif,webp',
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000'
            ],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Please select an avatar image.',
            'avatar.image' => 'Avatar must be an image file.',
            'avatar.max' => 'Avatar file size cannot exceed 2MB.',
            'avatar.mimes' => 'Avatar must be a JPEG, PNG, JPG, GIF, or WebP image.',
            'avatar.dimensions' => 'Avatar must be between 100x100 and 2000x2000 pixels.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'profile photo',
        ];
    }
} 