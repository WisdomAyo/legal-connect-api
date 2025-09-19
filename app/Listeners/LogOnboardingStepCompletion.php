<?php

namespace App\Listeners;

use App\Events\OnboardingStepCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class LogOnboardingStepCompletion implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(OnboardingStepCompleted $event)
    {
        Log::info('Onboarding step completed', [
            'user_id' => $event->user->id,
            'user_email' => $event->user->email,
            'step' => $event->step,
            'timestamp' => now()->toIso8601String(),
        ]);

        // Additional logic can be added here:
        // - Send notification emails
        // - Update analytics
        // - Trigger webhooks
        // - Update user metrics
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(OnboardingStepCompleted $event, $exception)
    {
        Log::error('Failed to log onboarding step completion', [
            'user_id' => $event->user->id,
            'step' => $event->step,
            'error' => $exception->getMessage(),
        ]);
    }
}
