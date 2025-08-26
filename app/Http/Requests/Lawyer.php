<?php

namespace App\Http\Requests\Lawyer;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOnboardingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Ensures only the logged-in user can update their own profile
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     * We use 'sometimes' so that you can submit partial data for each step.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            // Example rules for different steps
            // Step 1: Personal/Bio
            'bio' => 'sometimes|required|string|min:100',
            'office_address' => 'sometimes|required|string|max:255',

            // Step 2: Practice Details
            'practice_area_ids' => 'sometimes|required|array',
            'practice_area_ids.*' => 'exists:practice_areas,id', // Assuming you have a practice_areas table

            // Step 3: Document Uploads
            'identity_document' => 'sometimes|required|file|mimes:pdf,jpg,png|max:2048',
            'nba_certificate' => 'sometimes|required|file|mimes:pdf,jpg,png|max:2048',
        ];
    }
}
