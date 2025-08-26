<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use Illuminate\Support\Facades\Storage;
use App\Services\OnboardingStepInterface;

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
        if (!$profile) return 0;

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
            'graduation_year' => 'required|integer|min:1950|max:' . date('Y'),
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
        if (!$profile) return 0;

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
        if (!$profile) return 0;

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
        if (!$profile) return 0;

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

/**
 * Step 5: Documents & Verification
 */
class DocumentsAndVerificationStep implements OnboardingStepInterface
{
    public function getStepNumber(): int
    {
        return 5;
    }

    public function getMetadata(): array
    {
        return [
            'title' => 'Documents & Verification',
            'description' => 'Upload required documents for profile verification',
            'required_fields' => [
                'bar_license_document',
            ],
            'optional_fields' => [
                'law_degree_document',
                'professional_liability_insurance',
                'additional_certifications',
            ],
        ];
    }

    public function getValidationRules(): array
    {
        return [
            'bar_license_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB
            'law_degree_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'professional_liability_insurance' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'additional_certifications' => 'nullable|array',
            'additional_certifications.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function isComplete(User $user): bool
    {
        $profile = $user->lawyerProfile;
        return $profile && $profile->bar_license_document;
    }

    public function getCompletionPercentage(User $user): int
    {
        $profile = $user->lawyerProfile;
        if (!$profile) return 0;

        return $profile->bar_license_document ? 100 : 0;
    }

    public function isSkippable(): bool
    {
        return false;
    }

    public function save(User $user, array $data): void
    {
        $profile = $user->lawyerProfile;
        $documents = [];

        // Handle bar license document
        if (isset($data['bar_license_document'])) {
            $documents['bar_license_document'] = $data['bar_license_document']
                ->store('lawyer-documents', 'private');
        }

        // Handle optional documents
        $optionalDocs = ['law_degree_document', 'professional_liability_insurance'];
        foreach ($optionalDocs as $docType) {
            if (isset($data[$docType])) {
                $documents[$docType] = $data[$docType]->store('lawyer-documents', 'private');
            }
        }

        // Handle additional certifications
        if (isset($data['additional_certifications'])) {
            $certificationPaths = [];
            foreach ($data['additional_certifications'] as $cert) {
                $certificationPaths[] = $cert->store('lawyer-documents', 'private');
            }
            $documents['additional_certifications'] = $certificationPaths;
        }

        $profile->update($documents);
    }

    public function getData(User $user): array
    {
        $profile = $user->lawyerProfile;

        return [
            'bar_license_document' => $profile?->bar_license_document,
            'law_degree_document' => $profile?->law_degree_document,
            'professional_liability_insurance' => $profile?->professional_liability_insurance,
            'additional_certifications' => $profile?->additional_certifications ?? [],
        ];
    }
}
