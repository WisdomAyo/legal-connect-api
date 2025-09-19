<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
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
            'lawyer_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:5000',
            'appointment_id' => 'nullable|integer|exists:appointments,id',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,doc,docx|max:5120', // 5MB max per file
        ];
    }
}
