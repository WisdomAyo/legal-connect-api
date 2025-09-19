<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'exists:users,email'],
            'code' => ['required', 'string'],
            'password' => [
                'required',
                'string',
                'min:8',
                //  'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
                'confirmed',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'No account found with this email address.',
            'code.required' => 'Reset code is required.',
            //  'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
