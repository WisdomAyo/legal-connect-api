<?php
namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SignupLawyerRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:users',
            'phone_number' => 'required|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'state_id' => 'nullable|exists:states,id',
            'city_id' => 'nullable|exists:cities,id',
            'password' => 'required|string|min:6|confirmed',
            'nba_enrollment_number' => 'nullable|string|unique:lawyer_profiles,nba_enrollment_number',
            'year_of_call' => 'nullable|integer|min:1950|max:' . now()->year,
            'bio' => 'nullable|string',
            'office_address' => 'nullable|string',
            'profile_picture' => 'nullable|image|max:2048',
        ];
    }
}
