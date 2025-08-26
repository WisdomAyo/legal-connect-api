<?php

namespace App\Exceptions;

use Exception;

class OnboardingException extends Exception
{
     protected $statusCode;

    public function __construct(
        string $message = '',
        int $statusCode = 400,
        int $code = 0,
        Exception $previous = null
    ) {
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the HTTP status code for the exception
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Create an exception for invalid step
     */
    public static function invalidStep(int $step): self
    {
        return new self("Invalid onboarding step: {$step}", 400);
    }

    /**
     * Create an exception for incomplete profile
     */
    public static function incompleteProfile(array $incompleteSteps): self
    {
        $stepsList = implode(', ', $incompleteSteps);
        return new self(
            "Profile is not complete. Please complete the following steps: {$stepsList}",
            422
        );
    }

    /**
     * Create an exception for non-skippable step
     */
    public static function nonSkippableStep(int $step): self
    {
        return new self("Step {$step} cannot be skipped", 400);
    }

    /**
     * Create an exception for validation errors
     */
    public static function validationFailed(array $errors): self
    {
        return new self("Validation failed", 422);
    }

    /**
     * Create an exception for step handler interface issues
     */
    public static function invalidStepHandler(string $stepClass): self
    {
        return new self(
            "Step handler {$stepClass} must implement OnboardingStepInterface",
            500
        );
    }

    /**
     * Create an exception for already submitted profiles
     */
    public static function alreadySubmitted(): self
    {
        return new self("Profile has already been submitted for review", 409);
    }

    /**
     * Create an exception for access denied scenarios
     */
    public static function accessDenied(string $reason = ''): self
    {
        $message = 'Access denied' . ($reason ? ": {$reason}" : '');
        return new self($message, 403);
    }

    /**
     * Create an exception for step dependency issues
     */
    public static function dependencyNotMet(int $step, array $requiredSteps): self
    {
        $stepsList = implode(', ', $requiredSteps);
        return new self(
            "Step {$step} requires completion of steps: {$stepsList}",
            400
        );
    }
}
