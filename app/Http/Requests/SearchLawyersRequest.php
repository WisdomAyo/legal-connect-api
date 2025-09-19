<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchLawyersRequest extends FormRequest
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
            'query' => 'nullable|string|max:255',
            'specialization' => 'nullable|integer|exists:specializations,id',
            'practice_area' => 'nullable|integer|exists:practice_areas,id',
            'city' => 'nullable|string',
            'state_id' => 'nullable|integer|exists:states,id',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'max_consultation_fee' => 'nullable|numeric|min:0',
            'min_experience' => 'nullable|integer|min:0',
            'language' => 'nullable|integer|exists:languages,id',
            'availability_day' => 'nullable|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'sort_by' => 'nullable|string|in:rating,experience,fee,distance',
            'per_page' => 'nullable|integer|min:1|max:50',
        ];
    }
}
