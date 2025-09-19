<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LawyerUpdateAvailabilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'availability' => 'required|array',
            'availability.*' => 'nullable|array', // Each day can be null (not provided)
            'availability.*.start' => 'required_with:availability.*|date_format:H:i',
            'availability.*.end' => 'required_with:availability.*|date_format:H:i|after:availability.*.start',
        ];
    }
}
