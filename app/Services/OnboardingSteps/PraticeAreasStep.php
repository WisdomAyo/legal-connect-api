<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use App\Services\OnboardingStepInterface;

/**
 * Step 3: Practice Areas & Specializations
 */
class PracticeAreasStep implements OnboardingStepInterface
{
    public function getStepNumber(): int
    {
        return 3;
    }

    public function getMetadata(): array
    {
        return [
            'title' => 'Practice Areas & Specializations',
            'description' => 'Select your areas of legal expertise and specializations',
            'required_fields' => [
                'practice_area_ids',
            ],
            'optional_fields' => [
                'specialization_ids',
                'language_ids',
                'years_of_experience',
            ],
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'practice_area_ids' => 'required|array|min:1|max:5',
            'practice_area_ids.*' => 'exists:practice_areas,id',
            'specialization_ids' => 'nullable|array|max:10',
            'specialization_ids.*' => 'exists:specializations,id',
            'language_ids' => 'nullable|array|max:10',
            'language_ids.*' => 'exists:languages,id',
            'years_of_experience' => 'nullable|integer|min:0|max:70',
        ];
    }

    public function isComplete(User $user): bool
    {
        $profile = $user->lawyerProfile;

        return $profile && $profile->practiceAreas()->count() > 0;
    }

    public function getCompletionPercentage(User $user): int
    {
        $profile = $user->lawyerProfile;
        if (! $profile) {
            return 0;
        }

        $hasRequiredFields = $profile->practiceAreas()->count() > 0;

        return $hasRequiredFields ? 100 : 0;
    }

    public function isSkippable(): bool
    {
        return false;
    }

    public function save(User $user, array $data): void
    {
        $profile = $user->lawyerProfile;

        // Sync practice areas
        $profile->practiceAreas()->sync($data['practice_area_ids']);

        // Sync specializations if provided
        if (isset($data['specialization_ids'])) {
            $profile->specializations()->sync($data['specialization_ids']);
        }

        // Sync languages if provided
        if (isset($data['language_ids'])) {
            $profile->languages()->sync($data['language_ids']);
        }

        // Update years of experience
        if (isset($data['years_of_experience'])) {
            $profile->update(['years_of_experience' => $data['years_of_experience']]);
        }
    }

    public function getData(User $user): array
    {
        $profile = $user->lawyerProfile;

        return [
            'practice_area_ids' => $profile?->practiceAreas()->pluck('practice_areas.id')->toArray() ?? [],
            'specialization_ids' => $profile?->specializations()->pluck('specializations.id')->toArray() ?? [],
            'language_ids' => $profile?->languages()->pluck('languages.id')->toArray() ?? [],
            'years_of_experience' => $profile?->years_of_experience,
        ];
    }
}
