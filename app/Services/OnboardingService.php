<?php

namespace App\Services;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\LawyerRepository;
use App\Services\OnboardingStepInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use App\Exceptions\OnboardingException;
use App\Services\OnboardingSteps\PersonalInformationStep;
use App\Services\OnboardingSteps\ProfessionalCredentialsStep;
use App\Services\OnboardingSteps\PracticeAreasStep;
use App\Services\OnboardingSteps\FeesAndAvailabilityStep;
use App\Services\OnboardingSteps\DocumentsAndVerificationStep;

class OnboardingService
{
    protected array $steps = [
        1 => PersonalInformationStep::class,
        2 => ProfessionalCredentialsStep::class,
        3 => PracticeAreasStep::class,
        4 => FeesAndAvailabilityStep::class,
        5 => DocumentsAndVerificationStep::class,
    ];

    public function __construct(protected LawyerRepository $lawyerRepository)
    {
    }

    /**
     * Get comprehensive onboarding status with step-by-step progress
     */
    public function getStatus(User $user): JsonResponse
    {
        $user->load('lawyerProfile.practiceAreas', 'lawyerProfile.specializations', 'lawyerProfile.languages');

        $stepStatuses = $this->getStepStatuses($user);
        $overallProgress = $this->calculateOverallProgress($stepStatuses);

        return response()->json([
            'message' => 'Onboarding status fetched successfully.',
            'data' => [
                'user' => new UserResource($user),
                'onboarding' => [
                    'current_step' => $this->getCurrentStep($stepStatuses),
                    'overall_progress' => $overallProgress,
                    'is_complete' => $overallProgress === 100,
                    'steps' => $stepStatuses,
                    'next_action' => $this->getNextAction($stepStatuses, $user),
                ]
            ]
        ]);
    }

    /**
     * Save data for a specific onboarding step
     */
    public function saveStepData(User $user, int $step, array $validatedData): JsonResponse
    {
        if (!isset($this->steps[$step])) {
            throw new OnboardingException("Invalid onboarding step: {$step}");
        }

        $stepHandler = app($this->steps[$step]);

        if (!$stepHandler instanceof OnboardingStepInterface) {
            throw new OnboardingException("Step handler must implement OnboardingStepInterface");
        }

        DB::transaction(function () use ($user, $stepHandler, $validatedData) {
            $stepHandler->save($user, $validatedData);
            $this->updateStepCompletion($user, $stepHandler->getStepNumber());
        });

        // Get updated status for response
        $stepStatuses = $this->getStepStatuses($user);
        $nextStep = $this->getNextIncompleteStep($stepStatuses);

        return response()->json([
            'message' => "Step {$step} completed successfully.",
            'data' => [
                'completed_step' => $step,
                'next_step' => $nextStep,
                'overall_progress' => $this->calculateOverallProgress($stepStatuses),
                'can_submit' => $this->canSubmitForReview($stepStatuses),
            ]
        ]);
    }

    /**
     * Get validation rules for a specific step
     */
    public function getStepValidationRules(int $step): array
    {
        if (!isset($this->steps[$step])) {
            throw new OnboardingException("Invalid onboarding step: {$step}");
        }

        $stepHandler = app($this->steps[$step]);
        return $stepHandler->getValidationRules();
    }

    /**
     * Get step metadata (title, description, fields, etc.)
     */
    public function getStepMetadata(int $step): array
    {
        if (!isset($this->steps[$step])) {
            throw new OnboardingException("Invalid onboarding step: {$step}");
        }

        $stepHandler = app($this->steps[$step]);
        return $stepHandler->getMetadata();
    }

    /**
     * Get all steps metadata
     */
    public function getAllStepsMetadata(): array
    {
        return collect($this->steps)->map(function ($stepClass, $stepNumber) {
            $stepHandler = app($stepClass);
            return array_merge($stepHandler->getMetadata(), [
                'step_number' => $stepNumber,
            ]);
        })->values()->toArray();
    }

    /**
     * Submit profile for review (only if all steps are complete)
     */
    public function submitForReview(User $user): JsonResponse
    {
        $stepStatuses = $this->getStepStatuses($user);

        if (!$this->canSubmitForReview($stepStatuses)) {
            $incompleteSteps = collect($stepStatuses)
                ->where('is_complete', false)
                ->pluck('title')
                ->toArray();

            throw new OnboardingException(
                'Profile is not complete. Please complete the following steps: ' .
                implode(', ', $incompleteSteps)
            );
        }

        DB::transaction(function () use ($user) {
            $this->lawyerRepository->submitForReview($user);

            // Log submission for audit trail
            activity()
                ->performedOn($user)
                ->causedBy($user)
                ->withProperties(['status' => 'submitted_for_review'])
                ->log('Lawyer profile submitted for review');
        });

        return response()->json([
            'message' => 'Profile submitted for review successfully. You will be notified once it is approved.',
            'data' => [
                'status' => 'under_review',
                'submitted_at' => now()->toISOString(),
            ]
        ]);
    }

    /**
     * Skip a step (if allowed)
     */
    public function skipStep(User $user, int $step, string $reason = null): JsonResponse
    {
        if (!isset($this->steps[$step])) {
            throw new OnboardingException("Invalid onboarding step: {$step}");
        }

        $stepHandler = app($this->steps[$step]);

        if (!$stepHandler->isSkippable()) {
            throw new OnboardingException("Step {$step} cannot be skipped");
        }

        DB::transaction(function () use ($user, $step, $reason) {
            $this->markStepAsSkipped($user, $step, $reason);
        });

        return response()->json([
            'message' => "Step {$step} skipped successfully.",
            'data' => [
                'skipped_step' => $step,
                'next_step' => $this->getNextIncompleteStep($this->getStepStatuses($user)),
            ]
        ]);
    }

    /**
     * Get status for each onboarding step
     */
    protected function getStepStatuses(User $user): Collection
    {
        return collect($this->steps)->map(function ($stepClass, $stepNumber) use ($user) {
            $stepHandler = app($stepClass);
            $metadata = $stepHandler->getMetadata();

            return [
                'step_number' => $stepNumber,
                'title' => $metadata['title'],
                'description' => $metadata['description'],
                'is_complete' => $stepHandler->isComplete($user),
                'is_skippable' => $stepHandler->isSkippable(),
                'is_skipped' => $this->isStepSkipped($user, $stepNumber),
                'completion_percentage' => $stepHandler->getCompletionPercentage($user),
                'required_fields' => $metadata['required_fields'] ?? [],
                'optional_fields' => $metadata['optional_fields'] ?? [],
            ];
        })->values();
    }

    /**
     * Calculate overall onboarding progress percentage
     */
    protected function calculateOverallProgress(Collection $stepStatuses): int
    {
        $totalSteps = $stepStatuses->count();
        $completedOrSkippedSteps = $stepStatuses->filter(function ($step) {
            return $step['is_complete'] || $step['is_skipped'];
        })->count();

        return $totalSteps > 0 ? round(($completedOrSkippedSteps / $totalSteps) * 100) : 0;
    }

    /**
     * Get current active step
     */
    protected function getCurrentStep(Collection $stepStatuses): ?int
    {
        $incompleteStep = $stepStatuses->first(function ($step) {
            return !$step['is_complete'] && !$step['is_skipped'];
        });

        return $incompleteStep ? $incompleteStep['step_number'] : null;
    }

    /**
     * Get next incomplete step
     */
    protected function getNextIncompleteStep(Collection $stepStatuses): ?int
    {
        return $this->getCurrentStep($stepStatuses);
    }

    /**
     * Determine next action for user
     */
    protected function getNextAction(Collection $stepStatuses, User $user): array
    {
        $currentStep = $this->getCurrentStep($stepStatuses);

        if ($currentStep) {
            return [
                'type' => 'complete_step',
                'step' => $currentStep,
                'message' => "Complete step {$currentStep} to continue",
            ];
        }

        if ($this->canSubmitForReview($stepStatuses)) {
            return [
                'type' => 'submit_for_review',
                'message' => 'All steps completed. Submit your profile for review.',
            ];
        }

        return [
            'type' => 'profile_complete',
            'message' => 'Your profile is complete and under review.',
        ];
    }

    /**
     * Check if profile can be submitted for review
     */
    protected function canSubmitForReview(Collection $stepStatuses): bool
    {
        return $stepStatuses->every(function ($step) {
            return $step['is_complete'] || $step['is_skipped'];
        });
    }

    /**
     * Update step completion status
     */
    protected function updateStepCompletion(User $user, int $step): void
    {
        $profile = $user->lawyerProfile;
        $completedSteps = $profile->completed_onboarding_steps ?? [];

        if (!in_array($step, $completedSteps)) {
            $completedSteps[] = $step;
            $profile->update(['completed_onboarding_steps' => $completedSteps]);
        }
    }

    /**
     * Mark step as skipped
     */
    protected function markStepAsSkipped(User $user, int $step, ?string $reason): void
    {
        $profile = $user->lawyerProfile;
        $skippedSteps = $profile->skipped_onboarding_steps ?? [];

        $skippedSteps[$step] = [
            'skipped_at' => now()->toISOString(),
            'reason' => $reason,
        ];

        $profile->update(['skipped_onboarding_steps' => $skippedSteps]);
    }

    /**
     * Check if step is skipped
     */
    protected function isStepSkipped(User $user, int $step): bool
    {
        $skippedSteps = $user->lawyerProfile->skipped_onboarding_steps ?? [];
        return isset($skippedSteps[$step]);
    }
}
