<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SummarizeTextRequest extends FormRequest
{
    public function authorize(): bool
    {
        // This endpoint is behind auth:sanctum in routes; allow any authenticated user
        return true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:50', 'max:20000'],
            'maxSentences' => ['nullable', 'integer', 'min:1', 'max:10'],
            'format' => ['nullable', 'in:json,markdown'],
            'timezone' => ['nullable', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Please provide the text to summarize.',
            'text.min' => 'The text must be at least :min characters for a meaningful summary.',
            'text.max' => 'The text may not be greater than :max characters.',
        ];
    }
}
