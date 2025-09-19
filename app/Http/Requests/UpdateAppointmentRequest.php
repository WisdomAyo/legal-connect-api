<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentRequest extends FormRequest
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
            'status' => 'required|string|in:confirmed,cancelled,completed',
            'notes' => 'nullable|string|max:1000',
            'cancellation_reason' => 'required_if:status,cancelled|string|max:500',
            'reschedule_date' => 'nullable|date|after:now',
            'reschedule_time' => 'nullable|date_format:H:i',
        ];
    }
}
