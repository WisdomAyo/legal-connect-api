<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupLawyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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
                'required',
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
}
