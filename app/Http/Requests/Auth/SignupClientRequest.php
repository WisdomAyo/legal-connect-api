<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupClientRequest extends FormRequest
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
            'first_name' => [
                'required',
                'string',
                'max:255',
                // 'regex:/^[a-zA-Z\s\-\']+$/' // Only letters, spaces, hyphens, apostrophes
            ],
            'last_name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z\s\-\']+$/',
            ],
            'email' => [
                'required',
                'string',
                'email', // Standard email validation
                'max:255',
                'unique:users,email',
            ],
            'phone_number' => [
                'nullable',
                'string',
                // 'regex:/^(\+234|0)[789][01]\d{8}$/', // Nigerian phone format
                'unique:users,phone_number',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                // 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', // Must have lowercase, uppercase, number
                'confirmed', // Must match password_confirmation field
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'First name can only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.regex' => 'Last name can only contain letters, spaces, hyphens, and apostrophes.',
            'email.unique' => 'This email address is already registered.',
            'phone_number.unique' => 'This phone number is already registered.',
            // 'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
