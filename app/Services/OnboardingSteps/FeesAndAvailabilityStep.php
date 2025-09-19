<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use App\Services\OnboardingStepInterface;

/**
 * Step 4: Fees & Availability
 */
class FeesAndAvailabilityStep implements OnboardingStepInterface
{
    public function getStepNumber(): int
    {
        return 4;
    }

    public function getMetadata(): array
    {
        return [
            'title' => 'Fees & Availability',
            'description' => 'Set your consultation fees and availability preferences',
            'required_fields' => [
                'consultation_fee',
                'currency',
            ],
            'optional_fields' => [
                'availability_hours',
                'time_zone',
                'consultation_types',
            ],
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'consultation_fee' => 'required|numeric|min:0|max:10000',
            'currency' => 'required|string|size:3', // USD, EUR, etc.
            'availability_hours' => 'nullable|array',
            'availability_hours.*' => 'array:day,start_time,end_time',
            'time_zone' => 'nullable|string|max:50',
            'consultation_types' => 'nullable|array',
            'consultation_types.*' => 'in:phone,video,in_person',
        ];
    }

    public function isComplete(User $user): bool
    {
        $profile = $user->lawyerProfile;

        return $profile &&
               $profile->consultation_fee !== null &&
               $profile->currency;
    }

    public function getCompletionPercentage(User $user): int
    {
        $profile = $user->lawyerProfile;
        if (! $profile) {
            return 0;
        }

        $requiredFields = [
            $profile->consultation_fee,
            $profile->currency,
        ];

        $completedFields = array_filter($requiredFields, function ($field) {
            return $field !== null && $field !== '';
        });

        return round((count($completedFields) / count($requiredFields)) * 100);
    }

    public function isSkippable(): bool
    {
        return false;
    }

    public function save(User $user, array $data): void
    {
        $user->lawyerProfile()->updateOrCreate([], [
            'consultation_fee' => $data['consultation_fee'],
            'currency' => $data['currency'],
            'availability_hours' => $data['availability_hours'] ?? [],
            'time_zone' => $data['time_zone'] ?? null,
            'consultation_types' => $data['consultation_types'] ?? [],
        ]);
    }

    public function getData(User $user): array
    {
        $profile = $user->lawyerProfile;

        return [
            'consultation_fee' => $profile?->consultation_fee,
            'currency' => $profile?->currency,
            'availability_hours' => $profile?->availability_hours ?? [],
            'time_zone' => $profile?->time_zone,
            'consultation_types' => $profile?->consultation_types ?? [],
        ];
    }
}
