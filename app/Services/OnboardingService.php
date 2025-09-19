<?php

// app/Services/OnboardingService.php

namespace App\Services;

use App\Events\OnboardingCompleted;
use App\Events\OnboardingStepCompleted;
use App\Models\User;
use App\Repositories\LawyerRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * OnboardingService - Handles lawyer onboarding business logic
 *
 * Design Pattern: Service Layer
 * Why: Complex multi-step process needs centralized logic
 * Benefits: Reusable, testable, maintains single responsibility
 */
class OnboardingService
{
    // Define onboarding steps configuration
    private array $stepsConfig = [
        'personal_info' => [
            'order' => 1,
            'title' => 'Personal Information',
            'description' => 'Your contact details and office location',
            'required' => true,
            'skippable' => false,
            'icon' => 'user',
        ],
        'professional_info' => [
            'order' => 2,
            'title' => 'Professional Credentials',
            'description' => 'Your legal qualifications and areas of practice',
            'required' => true,
            'skippable' => false,
            'icon' => 'briefcase',
        ],
        'documents' => [
            'order' => 3,
            'title' => 'Document Upload',
            'description' => 'Upload your NBA certificate and CV',
            'required' => true,
            'skippable' => false,
            'icon' => 'file',
        ],
        'availability' => [
            'order' => 4,
            'title' => 'Availability & Fees',
            'description' => 'Set your consultation hours and pricing',
            'required' => false,
            'skippable' => true,
            'icon' => 'calendar',
        ],
    ];

    public function __construct(
        private LawyerRepository $lawyerRepository
    ) {}

    /**
     * Get comprehensive onboarding status
     */
    public function getStatus(User $user): array
    {
        $this->validateIsLawyer($user);

        $steps = $this->getAllStepsStatus($user);
        $progress = $this->calculateProgress($user);
        $currentStep = $this->getCurrentStep($user);

        return [
            'success' => true,
            'data' => [
                'overall_progress' => $progress['percentage'],
                'completed_steps' => $progress['completed'],
                'total_steps' => $progress['total'],
                'current_step' => $currentStep,
                'steps' => $steps,
                'can_submit' => $this->canSubmitForReview($user),
                'profile_status' => $user->lawyerProfile->status,
                'estimated_completion_time' => $this->estimateCompletionTime($progress),
            ],
        ];
    }

    /**
     * Get all steps metadata
     */
    public function getStepsMetadata(): array
    {
        $steps = [];

        foreach ($this->stepsConfig as $name => $config) {
            $steps[] = array_merge($config, [
                'name' => $name,
                'validation_rules' => $this->getValidationRulesForStep($name),
            ]);
        }

        return [
            'success' => true,
            'data' => $steps,
        ];
    }

    /**
     * Get specific step metadata
     */
    public function getStepMetadata(string $step): array
    {
        if (! isset($this->stepsConfig[$step])) {
            throw ValidationException::withMessages([
                'step' => ["Invalid step: {$step}"],
            ]);
        }

        return [
            'success' => true,
            'data' => array_merge($this->stepsConfig[$step], [
                'name' => $step,
                'validation_rules' => $this->getValidationRulesForStep($step),
            ]),
        ];
    }

    /**
     * Get validation rules for a step
     */
    public function getStepValidationRules(string $step): array
    {
        if (! isset($this->stepsConfig[$step])) {
            throw ValidationException::withMessages([
                'step' => ["Invalid step: {$step}"],
            ]);
        }

        return [
            'success' => true,
            'data' => $this->getValidationRulesForStep($step),
        ];
    }

    /**
     * Get saved data for a step
     */
    public function getStepData(User $user, string $step): array
    {
        $this->validateIsLawyer($user);

        $stepRecord = $user->onboardingSteps()
            ->where('step_name', $step)
            ->first();

        // Also get data from profile if applicable
        $profileData = $this->getProfileDataForStep($user, $step);

        return [
            'success' => true,
            'data' => [
                'step' => $step,
                'saved_data' => $stepRecord?->step_data ?? [],
                'profile_data' => $profileData,
                'is_completed' => $stepRecord?->is_completed ?? false,
                'is_skipped' => $stepRecord?->is_skipped ?? false,
            ],
        ];
    }

    /**
     * Save data for a specific step
     */
    public function saveStepData(User $user, string $step, array $data): array
    {
        $this->validateIsLawyer($user);

        if (! isset($this->stepsConfig[$step])) {
            throw ValidationException::withMessages([
                'step' => ["Invalid step: {$step}"],
            ]);
        }

        DB::beginTransaction();

        try {
            // Get or create step record
            $stepRecord = $user->onboardingSteps()->firstOrCreate(
                ['step_name' => $step],
                ['step_data' => []]
            );

            // Save data based on step type
            switch ($step) {
                case 'personal_info':
                    $this->savePersonalInfo($user, $data);
                    break;

                case 'professional_info':
                    $this->saveProfessionalInfo($user, $data);
                    break;

                case 'documents':
                    $this->saveDocuments($user, $data);
                    break;

                case 'availability':
                    $this->saveAvailability($user, $data);
                    break;
            }

            // Mark step as completed
            $stepRecord->markAsCompleted($data);

            // Update profile status
            $this->updateProfileStatus($user);

            // Fire event
            event(new OnboardingStepCompleted($user, $step));

            // Log activity
            Log::info('Onboarding step completed', [
                'user_id' => $user->id,
                'step' => $step,
            ]);

            DB::commit();

            $nextStep = $this->getNextStep($user);
            $progress = $this->calculateProgress($user);

            return [
                'success' => true,
                'message' => 'Step saved successfully',
                'data' => [
                    'completed_step' => $step,
                    'next_step' => $nextStep,
                    'overall_progress' => $progress['percentage'],
                    'can_submit' => $this->canSubmitForReview($user),
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to save onboarding step', [
                'user_id' => $user->id,
                'step' => $step,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Skip a step (if allowed)
     */
    public function skipStep(User $user, string $step, ?string $reason): array
    {
        $this->validateIsLawyer($user);

        if (! $this->stepsConfig[$step]['skippable']) {
            throw ValidationException::withMessages([
                'step' => ["Step '{$step}' cannot be skipped as it is required"],
            ]);
        }

        $stepRecord = $user->onboardingSteps()?->firstOrCreate(
            ['step_name' => $step],
            ['step_data' => []]
        );

        $stepRecord->markAsSkipped($reason);

        return [
            'success' => true,
            'message' => 'Step skipped',
            'data' => [
                'skipped_step' => $step,
                'next_step' => $this->getNextStep($user),
            ],
        ];
    }

    /**
     * Submit profile for review
     */
    public function submitForReview(User $user): array
    {
        $this->validateIsLawyer($user);

        if (! $this->canSubmitForReview($user)) {
            $missingSteps = $this->getMissingRequiredSteps($user);

            throw ValidationException::withMessages([
                'profile' => [
                    'Please complete all required steps before submitting.',
                    'Missing steps: '.implode(', ', $missingSteps),
                ],
            ]);
        }

        DB::beginTransaction();

        try {
            // Update profile status
            $user->lawyerProfile->submitForReview();

            // Fire event
            event(new OnboardingCompleted($user));

            // Log submission
            Log::info('Lawyer profile submitted for review', [
                'user_id' => $user->id,
                'profile_id' => $user->lawyerProfile->id,
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Your profile has been submitted for review',
                'data' => [
                    'status' => 'pending_review',
                    'estimated_review_time' => '24-48 hours',
                    'notification' => 'You will receive an email once your profile is reviewed',
                ],
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * PRIVATE HELPER METHODS
     */
    private function validateIsLawyer(User $user): void
    {
        if (! $user->hasRole('lawyer')) {
            throw ValidationException::withMessages([
                'user' => ['Onboarding is only available for lawyers'],
            ]);
        }
    }

    private function savePersonalInfo(User $user, array $data): void
    {
        // Update user model
        $user->update([
            'phone_number' => $data['phone_number'] ?? $user->phone_number,
            'country_id' => $data['country_id'] ?? $user->country_id,
            'state_id' => $data['state_id'] ?? $user->state_id,
            'city_id' => $data['city_id'] ?? $user->city_id,
        ]);

        // Update lawyer profile
        $user->lawyerProfile->update([
            'office_address' => $data['office_address'],
            'bio' => $data['bio'] ?? null,
        ]);
    }

    private function saveProfessionalInfo(User $user, array $data): void
    {
        $user->lawyerProfile->update([
            'nba_enrollment_number' => $data['nba_enrollment_number'],
            'year_of_call' => $data['year_of_call'],
            'law_school' => $data['law_school'],
            'graduation_year' => $data['graduation_year'],
        ]);

        // Sync relationships
        if (isset($data['practice_areas'])) {
            $user->lawyerProfile->practiceAreas()->sync($data['practice_areas']);
        }

        if (isset($data['specializations'])) {
            $user->lawyerProfile->specializations()->sync($data['specializations']);
        }

        if (isset($data['languages'])) {
            $user->lawyerProfile->languages()->sync($data['languages']);
        }
    }

    private function saveDocuments(User $user, array $data): void
{

    try {
        // NBA Certificate
        if (isset($data['nba_certificate']) && $data['nba_certificate'] instanceof \Illuminate\Http\UploadedFile) {
            if (! $data['nba_certificate']->isValid()) {
                throw ValidationException::withMessages([
                    'nba_certificate' => ['The NBA certificate upload is invalid.'],
                ]);
            }

            $path = $data['nba_certificate']->store(
                "lawyers/{$user->id}/documents",
                'public'
            );

            $user->lawyerProfile->update(['bar_certificate_path' => $path]);
        }

        // CV
        if (isset($data['cv']) && $data['cv'] instanceof \Illuminate\Http\UploadedFile) {
            if (! $data['cv']->isValid()) {
                throw ValidationException::withMessages([
                    'cv' => ['The CV upload is invalid.'],
                ]);
            }

            $path = $data['cv']->store(
                "lawyers/{$user->id}/documents",
                'public'
            );

            $user->lawyerProfile->update(['cv_path' => $path]);
        }

    } catch (\Exception $e) {
        Log::error('Document upload failed', [
            'user_id' => $user->id,
            'error'   => $e->getMessage(),
        ]);

        throw ValidationException::withMessages([
            'documents' => ['One or more documents failed to upload. Please try again.'],
        ]);
    }
}


    private function saveAvailability(User $user, array $data): void
    {
        $user->lawyerProfile->update([
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'consultation_fee' => $data['consultation_fee'],
            'availability' => $data['availability'],
        ]);
    }

    private function getAllStepsStatus(User $user): array
    {
        $steps = [];
        $userSteps = $user->onboardingSteps()->get()->keyBy('step_name');

        foreach ($this->stepsConfig as $name => $config) {
            $userStep = $userSteps->get($name);

            $steps[] = [
                'name' => $name,
                'title' => $config['title'],
                'description' => $config['description'],
                'order' => $config['order'],
                'required' => $config['required'],
                'skippable' => $config['skippable'],
                'icon' => $config['icon'],
                'is_completed' => $userStep?->is_completed ?? false,
                'is_skipped' => $userStep?->is_skipped ?? false,
                'completed_at' => $userStep?->completed_at,
            ];
        }

        return $steps;
    }

    private function calculateProgress(User $user): array
    {
        $totalSteps = count($this->stepsConfig);
        $requiredSteps = count(array_filter($this->stepsConfig, fn ($s) => $s['required']));

        $completedSteps = $user->onboardingSteps()
            ->where('is_completed', true)
            ->count();

        $skippedSteps = $user->onboardingSteps()
            ->where('is_skipped', true)
            ->count();

        $progress = $completedSteps + $skippedSteps;

        return [
            'completed' => $completedSteps,
            'skipped' => $skippedSteps,
            'total' => $totalSteps,
            'required' => $requiredSteps,
            'percentage' => round(($progress / $totalSteps) * 100),
        ];
    }

    private function getCurrentStep(User $user): ?string
    {
        foreach ($this->stepsConfig as $name => $config) {
            $step = $user->onboardingSteps()
                ->where('step_name', $name)
                ->first();

            if (! $step || (! $step->is_completed && ! $step->is_skipped)) {
                return $name;
            }
        }

        return null;
    }

   private function getNextStep(User $user): ?string
{
    foreach (array_keys($this->stepsConfig) as $step) {
        $record = $user->onboardingSteps()
            ->where('step_name', $step)
            ->first();

        if (! $record || (! $record->is_completed && ! $record->is_skipped)) {
            return $step; // first incomplete step
        }
    }

    return null;
}



    private function canSubmitForReview(User $user): bool
    {
        $requiredSteps = array_keys(array_filter(
            $this->stepsConfig,
            fn ($s) => $s['required']
        ));

        $completedRequiredSteps = $user->onboardingSteps()
            ->whereIn('step_name', $requiredSteps)
            ->where('is_completed', true)
            ->count();

        return $completedRequiredSteps === count($requiredSteps);
    }

    private function getMissingRequiredSteps(User $user): array
    {
        $requiredSteps = array_keys(array_filter(
            $this->stepsConfig,
            fn ($s) => $s['required']
        ));

        $completedSteps = $user->onboardingSteps()
            ->whereIn('step_name', $requiredSteps)
            ->where('is_completed', true)
            ->pluck('step_name')
            ->toArray();

        $missing = array_diff($requiredSteps, $completedSteps);

        return array_map(
            fn ($step) => $this->stepsConfig[$step]['title'],
            $missing
        );
    }

    private function getProfileDataForStep(User $user, string $step): array
    {
        $profile = $user->lawyerProfile;

        return match ($step) {
            'personal_info' => [
                'phone_number' => $user->phone_number,
                'country_id' => $user->country_id,
                'state_id' => $user->state_id,
                'city_id' => $user->city_id,
                'office_address' => $profile->office_address,
                'bio' => $profile->bio,
            ],
            'professional_info' => [
                'nba_enrollment_number' => $profile->nba_enrollment_number,
                'year_of_call' => $profile->year_of_call,
                'law_school' => $profile->law_school,
                'graduation_year' => $profile->graduation_year,
                'practice_areas' => $profile->practiceAreas->pluck('id')->toArray(),
                'specializations' => $profile->specializations->pluck('id')->toArray(),
                'languages' => $profile->languages->pluck('id')->toArray(),
            ],
            'documents' => [
                'has_nba_certificate' => ! empty($profile->bar_certificate_path),
                'has_cv' => ! empty($profile->cv_path),
            ],
            'availability' => [
                'hourly_rate' => $profile->hourly_rate,
                'consultation_fee' => $profile->consultation_fee,
                'availability' => $profile->availability,
            ],
            default => []
        };
    }

    private function estimateCompletionTime(array $progress): string
    {
        $remaining = $progress['total'] - ($progress['completed'] + $progress['skipped']);

        if ($remaining === 0) {
            return 'Ready for submission';
        }

        // Estimate 5 minutes per step
        $minutes = $remaining * 5;

        if ($minutes < 60) {
            return "{$minutes} minutes";
        }

        $hours = round($minutes / 60, 1);

        return "{$hours} hour".($hours > 1 ? 's' : '');
    }

    private function updateProfileStatus(User $user): void
    {
        $progress = $this->calculateProgress($user);

        if ($progress['percentage'] > 0 && $progress['percentage'] < 100) {
            $user->lawyerProfile->update(['status' => 'in_progress']);
        }
    }

    private function getValidationRulesForStep(string $step): array
    {
        return match ($step) {
            'personal_info' => [
                'phone_number' => 'required|string',
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
                'practice_areas' => 'required|array|min:1|max:5',
                'practice_areas.*' => 'exists:practice_areas,id',
                'specializations' => 'nullable|array|max:3',
                'specializations.*' => 'exists:specializations,id',
                'languages' => 'required|array|min:1',
                'languages.*' => 'exists:languages,id',
            ],
            'documents' => [
                'nba_certificate' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
                'cv' => 'required|file|mimes:pdf,doc,docx|max:5120',
            ],
            'availability' => [
                'hourly_rate' => 'nullable|numeric|min:1000|max:1000000',
                'consultation_fee' => 'required|numeric|min:1000|max:100000',
                'availability' => 'required|array',
                'availability.monday' => 'nullable|array',
                'availability.monday.start' => 'required_with:availability.monday|date_format:H:i',
                'availability.monday.end' => 'required_with:availability.monday|date_format:H:i|after:availability.monday.start',
                'availability.tuesday' => 'nullable|array',
                'availability.tuesday.start' => 'required_with:availability.tuesday|date_format:H:i',
                'availability.tuesday.end' => 'required_with:availability.tuesday|date_format:H:i|after:availability.tuesday.start',
                'availability.wednesday' => 'nullable|array',
                'availability.wednesday.start' => 'required_with:availability.wednesday|date_format:H:i',
                'availability.wednesday.end' => 'required_with:availability.wednesday|date_format:H:i|after:availability.wednesday.start',
                'availability.thursday' => 'nullable|array',
                'availability.thursday.start' => 'required_with:availability.thursday|date_format:H:i',
                'availability.thursday.end' => 'required_with:availability.thursday|date_format:H:i|after:availability.thursday.start',
                'availability.friday' => 'nullable|array',
                'availability.friday.start' => 'required_with:availability.friday|date_format:H:i',
                'availability.friday.end' => 'required_with:availability.friday|date_format:H:i|after:availability.friday.start',
                'availability.saturday' => 'nullable|array',
                'availability.saturday.start' => 'required_with:availability.saturday|date_format:H:i',
                'availability.saturday.end' => 'required_with:availability.saturday|date_format:H:i|after:availability.saturday.start',
                'availability.sunday' => 'nullable|array',
                'availability.sunday.start' => 'required_with:availability.sunday|date_format:H:i',
                'availability.sunday.end' => 'required_with:availability.sunday|date_format:H:i|after:availability.sunday.start',
            ],
            default => []
        };
    }
}
