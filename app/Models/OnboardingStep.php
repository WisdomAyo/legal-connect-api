<?php

// app/Models/OnboardingStep.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * OnboardingStep Model - Tracks lawyer onboarding progress
 *
 * Why separate model:
 * - Allows granular tracking of each step
 * - Enables resume from where user left off
 * - Provides analytics on drop-off points
 */
class OnboardingStep extends Model
{
    protected $fillable = [
        'user_id',
        'step_name',
        'step_data',
        'is_completed',
        'is_skipped',
        'skip_reason',
        'completed_at',
    ];

    protected $casts = [
        'step_data' => 'array',
        'is_completed' => 'boolean',
        'is_skipped' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * Available onboarding steps
     */
    const STEPS = [
        'personal_info' => [
            'order' => 1,
            'title' => 'Personal Information',
            'required' => true,
        ],
        'professional_info' => [
            'order' => 2,
            'title' => 'Professional Credentials',
            'required' => true,
        ],
        'documents' => [
            'order' => 3,
            'title' => 'Document Upload',
            'required' => true,
        ],
        'availability' => [
            'order' => 4,
            'title' => 'Availability & Fees',
            'required' => false,
        ],
    ];

    /**
     * RELATIONSHIPS
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * ACCESSORS
     */
    public function getStepTitleAttribute(): string
    {
        return self::STEPS[$this->step_name]['title'] ?? 'Unknown Step';
    }

    public function getStepOrderAttribute(): int
    {
        return self::STEPS[$this->step_name]['order'] ?? 999;
    }

    public function getIsRequiredAttribute(): bool
    {
        return self::STEPS[$this->step_name]['required'] ?? false;
    }

    /**
     * SCOPES
     */
    public function scopeCompleted($query)
    {
        return $query->where('is_completed', true);
    }

    public function scopeIncomplete($query)
    {
        return $query->where('is_completed', false)
            ->where('is_skipped', false);
    }

    public function scopeRequired($query)
    {
        $requiredSteps = array_keys(array_filter(
            self::STEPS,
            fn ($step) => $step['required']
        ));

        return $query->whereIn('step_name', $requiredSteps);
    }

    /**
     * HELPER METHODS
     */
    public function markAsCompleted(array $data = []): void
    {
        $this->update([
            'step_data' => array_merge($this->step_data ?? [], $data),
            'is_completed' => true,
            'is_skipped' => false,
            'completed_at' => now(),
        ]);
    }

    public function markAsSkipped(?string $reason = null): void
    {
        $this->update([
            'is_skipped' => true,
            'skip_reason' => $reason,
            'is_completed' => false,
        ]);
    }

    /**
     * Get validation rules for this step
     */
    public function getValidationRules(): array
    {
        return match ($this->step_name) {
            'personal_info' => [
                'phone_number' => 'required|string|regex:/^(\+234|0)[789][01]\d{8}$/',
                'country_id' => 'required|exists:countries,id',
                'state_id' => 'required|exists:states,id',
                'city_id' => 'required|exists:cities,id',
                'office_address' => 'required|string|max:500',
                'bio' => 'nullable|string|max:1000',
            ],
            'professional_info' => [
                'nba_enrollment_number' => 'required|string|unique:lawyer_profiles,nba_enrollment_number',
                'year_of_call' => 'required|integer|min:1960|max:'.date('Y'),
                'law_school' => 'required|string|max:255',
                'graduation_year' => 'required|integer|min:1960|max:'.date('Y'),
                'practice_areas' => 'required|array|min:1',
                'practice_areas.*' => 'exists:practice_areas,id',
                'specializations' => 'nullable|array',
                'specializations.*' => 'exists:specializations,id',
                'languages' => 'required|array|min:1',
                'languages.*' => 'exists:languages,id',
            ],
            'documents' => [
                'nba_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
            ],
            'availability' => [
                'hourly_rate' => 'nullable|numeric|min:0',
                'consultation_fee' => 'required|numeric|min:0',
                'availability' => 'required|array',
                'availability.*.start' => 'required|date_format:H:i',
                'availability.*.end' => 'required|date_format:H:i|after:availability.*.start',
            ],
            default => []
        };
    }
}
