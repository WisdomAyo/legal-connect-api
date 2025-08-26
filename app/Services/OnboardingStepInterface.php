<?php

namespace App\Services;

use App\Models\User;

interface OnboardingStepInterface
{
    /**
     * Get the step number
     */
    public function getStepNumber(): int;

    /**
     * Get step metadata (title, description, fields, etc.)
     */
    public function getMetadata(): array;

    /**
     * Get validation rules for this step
     */
    public function getValidationRules(): array;

    /**
     * Check if this step is complete for the given user
     */
    public function isComplete(User $user): bool;

    /**
     * Get completion percentage for this step (0-100)
     */
    public function getCompletionPercentage(User $user): int;

    /**
     * Check if this step can be skipped
     */
    public function isSkippable(): bool;

    /**
     * Save the step data for the user
     */
    public function save(User $user, array $data): void;

    /**
     * Get the current data for this step
     */
    public function getData(User $user): array;
}
