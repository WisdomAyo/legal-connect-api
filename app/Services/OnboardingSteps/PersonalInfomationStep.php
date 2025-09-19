<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use App\Services\OnboardingStepInterface;
use Illuminate\Support\Facades\Storage;

/**
 * Step 1: Personal Information
 */
class PersonalInformationStep implements OnboardingStepInterface
{
    public function getStepNumber(): int
    {
        return 1;
    }

    public function getMetadata(): array
    {
        return [
            'title' => 'Personal Information',
            'description' => 'Basic personal details and contact information',
            'required_fields' => [
                'first_name',
                'last_name',
                'phone',
                'date_of_birth',
                'address',
            ],
            'optional_fields' => [
                'profile_photo',
                'bio',
                'website',
            ],
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[\+]?[1-9][\d]{0,15}$/',
            'date_of_birth' => 'required|date|before:18 years ago',
            'address.street' => 'required|string|max:255',
            'address.city' => 'required|string|max:100',
            'address.state' => 'required|string|max:100',
            'address.postal_code' => 'required|string|max:20',
            'address.country' => 'required|string|max:100',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'bio' => 'nullable|string|max:1000',
            'website' => 'nullable|url|max:255',
        ];
    }

    public function isComplete(User $user): bool
    {
        $profile = $user->lawyerProfile;

        return $profile &&
               $user->first_name &&
               $user->last_name &&
               $profile->phone &&
               $profile->date_of_birth &&
               $profile->address &&
               is_array($profile->address) &&
               isset($profile->address['street'], $profile->address['city'],
                   $profile->address['state'], $profile->address['postal_code']);
    }

    public function getCompletionPercentage(User $user): int
    {
        $profile = $user->lawyerProfile;
        if (! $profile) {
            return 0;
        }

        $requiredFields = [
            $user->first_name,
            $user->last_name,
            $profile->phone,
            $profile->date_of_birth,
            $profile->address['street'] ?? null,
            $profile->address['city'] ?? null,
            $profile->address['state'] ?? null,
            $profile->address['postal_code'] ?? null,
        ];

        $completedFields = array_filter($requiredFields);

        return round((count($completedFields) / count($requiredFields)) * 100);
    }

    public function isSkippable(): bool
    {
        return false; // Personal info is mandatory
    }

    public function save(User $user, array $data): void
    {
        // Update user table
        $user->update([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ]);

        // Handle profile photo upload
        $profileData = [];
        if (isset($data['profile_photo'])) {
            $path = $data['profile_photo']->store('lawyer-profiles', 'public');
            $profileData['profile_photo'] = $path;
        }

        // Update lawyer profile
        $user->lawyerProfile()->updateOrCreate([], array_merge([
            'phone' => $data['phone'],
            'date_of_birth' => $data['date_of_birth'],
            'address' => $data['address'],
            'bio' => $data['bio'] ?? null,
            'website' => $data['website'] ?? null,
        ], $profileData));
    }

    public function getData(User $user): array
    {
        $profile = $user->lawyerProfile;

        return [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'phone' => $profile?->phone,
            'date_of_birth' => $profile?->date_of_birth,
            'address' => $profile?->address ?? [],
            'bio' => $profile?->bio,
            'website' => $profile?->website,
            'profile_photo_url' => $profile?->profile_photo ? Storage::url($profile->profile_photo) : null,
        ];
    }
}
