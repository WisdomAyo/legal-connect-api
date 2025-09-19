<?php

namespace App\Http\Requests\Shared;

use Illuminate\Foundation\Http\FormRequest;

class GetAppointmentsRequest extends FormRequest
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
     * These rules are a combination of what both clients and lawyers can filter by.
     */
    public function rules(): array
    {
        return [
            // Common rules
            'status' => 'nullable|string|in:pending,confirmed,completed,cancelled',

            // Client-specific filter
            'period' => 'nullable|string|in:upcoming,past,all',

            // Lawyer-specific filters
            'date' => 'nullable|date',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'client_id' => 'nullable|integer|exists:users,id',
        ];
    }
}
