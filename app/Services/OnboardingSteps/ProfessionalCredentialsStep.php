<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use App\Services\OnboardingStepInterface;

/**
 * Step 2: Professional Credentials
 */
class ProfessionalCredentialsStep implements OnboardingStepInterface
{
    public function getStepNumber(): int
    {
        return 2;
    }

    public function getMetadata(): array
    {
        return [
            'title' => 'Professional Credentials',
            'description' => 'Bar admission, education, and professional certifications',
            'required_fields' => [
                'bar_number',
                'bar_state',
                'bar_admission_date',
                'law_school',
                'graduation_year',
            ],
            'optional_fields' => [
                'other_bars',
                'certifications',
                'honors_awards',
            ],
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'bar_number' => 'required|string|max:50',
            'bar_state' => 'required|string|max:100',
            'bar_admission_date' => 'required|date|before:today',
            'law_school' => 'required|string|max:255',
            'graduation_year' => 'required|integer|min:1950|max:'.date('Y'),
            'other_bars' => 'nullable|array',
            'other_bars.*.state' => 'required_with:other_bars|string|max:100',
            'other_bars.*.number' => 'required_with:other_bars|string|max:50',
            'other_bars.*.admission_date' => 'required_with:other_bars|date|before:today',
            'certifications' => 'nullable|array',
            'certifications.*' => 'string|max:255',
            'honors_awards' => 'nullable|array',
            'honors_awards.*' => 'string|max:255',
        ];
    }

    public function isComplete(User $user): bool
    {
        $profile = $user->lawyerProfile;

        return $profile &&
               $profile->bar_number &&
               $profile->bar_state &&
               $profile->bar_admission_date &&
               $profile->law_school &&
               $profile->graduation_year;
    }

    public function getCompletionPercentage(User $user): int
    {
        $profile = $user->lawyerProfile;
        if (! $profile) {
            return 0;
        }

        $requiredFields = [
            $profile->bar_number,
            $profile->bar_state,
            $profile->bar_admission_date,
            $profile->law_school,
            $profile->graduation_year,
        ];

        $completedFields = array_filter($requiredFields);

        return round((count($completedFields) / count($requiredFields)) * 100);
    }

    public function isSkippable(): bool
    {
        return false;
    }

    public function save(User $user, array $data): void
    {
        $user->lawyerProfile()->updateOrCreate([], [
            'bar_number' => $data['bar_number'],
            'bar_state' => $data['bar_state'],
            'bar_admission_date' => $data['bar_admission_date'],
            'law_school' => $data['law_school'],
            'graduation_year' => $data['graduation_year'],
            'other_bars' => $data['other_bars'] ?? [],
            'certifications' => $data['certifications'] ?? [],
            'honors_awards' => $data['honors_awards'] ?? [],
        ]);
    }

    public function getData(User $user): array
    {
        $profile = $user->lawyerProfile;

        return [
            'bar_number' => $profile?->bar_number,
            'bar_state' => $profile?->bar_state,
            'bar_admission_date' => $profile?->bar_admission_date,
            'law_school' => $profile?->law_school,
            'graduation_year' => $profile?->graduation_year,
            'other_bars' => $profile?->other_bars ?? [],
            'certifications' => $profile?->certifications ?? [],
            'honors_awards' => $profile?->honors_awards ?? [],
        ];
    }
}
