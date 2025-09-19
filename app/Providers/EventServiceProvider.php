<?php

namespace App\Providers;

use App\Events\OnboardingCompleted;
use App\Events\OnboardingStepCompleted;
use App\Events\UserRegistered;
use App\Listeners\SendEmailVerificationNotification;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        // ... other events

        UserRegistered::class => [
            SendEmailVerificationNotification::class,
        ],

        OnboardingStepCompleted::class => [
            \App\Listeners\LogOnboardingStepCompletion::class,
        ],

        OnboardingCompleted::class => [
            \App\Listeners\HandleOnboardingCompletion::class,
        ],

        SocialiteWasCalled::class => [
            \SocialiteProviders\LinkedIn\LinkedInExtendSocialite::class.'@handel',
        ],

    ];

    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
