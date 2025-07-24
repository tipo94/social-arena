<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'email' => [
                'required', 
                'string', 
                'email:rfc,dns', 
                'max:255', 
                'unique:users,email'
            ],
            'password' => [
                'required', 
                'confirmed', 
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised()
            ],
            'username' => [
                'sometimes', 
                'string', 
                'min:3',
                'max:50', 
                'unique:users,username', 
                'regex:/^[a-zA-Z0-9_]+$/'
            ],
            'first_name' => ['sometimes', 'string', 'max:100'],
            'last_name' => ['sometimes', 'string', 'max:100'],
            'phone' => [
                'sometimes', 
                'string', 
                'max:20',
                'regex:/^[\+]?[1-9][\d]{0,15}$/' // International phone format
            ],
            'timezone' => ['sometimes', 'string', 'max:50', 'timezone'],
            'locale' => [
                'sometimes', 
                'string', 
                'max:10',
                'in:en,es,fr,de,it,pt,ja,ko,zh,ru,ar'
            ],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'marketing_emails' => ['sometimes', 'boolean'],
            'date_of_birth' => ['sometimes', 'date', 'before:today'],
            'gender' => [
                'sometimes', 
                'string',
                'in:male,female,non_binary,prefer_not_to_say,other'
            ],
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
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'username.unique' => 'This username is already taken.',
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'username.min' => 'Username must be at least 3 characters.',
            'phone.regex' => 'Please provide a valid phone number.',
            'timezone.timezone' => 'Please provide a valid timezone.',
            'locale.in' => 'Please select a valid language.',
            'terms_accepted.accepted' => 'You must accept the terms of service.',
            'privacy_accepted.accepted' => 'You must accept the privacy policy.',
            'date_of_birth.before' => 'Date of birth must be before today.',
            'gender.in' => 'Please select a valid gender option.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'password' => 'password',
            'username' => 'username',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'phone' => 'phone number',
            'timezone' => 'timezone',
            'locale' => 'language',
            'terms_accepted' => 'terms of service',
            'privacy_accepted' => 'privacy policy',
            'marketing_emails' => 'marketing emails preference',
            'date_of_birth' => 'date of birth',
            'gender' => 'gender',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        $response = response()->json([
            'success' => false,
            'message' => 'Registration validation failed',
            'errors' => $validator->errors(),
        ], 422);

        throw new \Illuminate\Validation\ValidationException($validator, $response);
    }
} 