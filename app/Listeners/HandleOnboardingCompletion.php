<?php

namespace App\Listeners;

use App\Events\OnboardingCompleted;
use App\Services\Security\AuditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class HandleOnboardingCompletion implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The audit service instance.
     *
     * @var \App\Services\Security\AuditService
     */
    protected $auditService;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(OnboardingCompleted $event)
    {
        $user = $event->user;

        // Log the completion
        Log::info('Lawyer onboarding completed', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'profile_id' => $user->lawyerProfile->id,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Create audit log entry
        $this->auditService->log(
            'onboarding_completed',
            $user,
            [
                'message' => 'Lawyer completed onboarding process',
                'profile_status' => $user->lawyerProfile->status,
                'completed_at' => now()->toIso8601String(),
            ]
        );

        // Send notification email (placeholder for future implementation)
        // Mail::to($user->email)->queue(new OnboardingCompletedMail($user));

        // Additional actions that can be implemented:
        // - Send welcome email with next steps
        // - Notify admin team for profile review
        // - Update user statistics/analytics
        // - Trigger CRM integration
        // - Schedule follow-up tasks
        // - Generate lawyer profile preview
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(OnboardingCompleted $event, $exception)
    {
        Log::error('Failed to handle onboarding completion', [
            'user_id' => $event->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
