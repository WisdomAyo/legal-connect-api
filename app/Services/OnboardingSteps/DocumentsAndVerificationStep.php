<?php

namespace App\Services\OnboardingSteps;

use App\Models\User;
use App\Services\OnboardingStepInterface;

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
        if (! $profile) {
            return 0;
        }

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
