<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Events\UserRegistered;
use App\Listeners\SendEmailVerificationNotification;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{


    protected $listen = [
    // ... other events

    UserRegistered::class => [
        SendEmailVerificationNotification::class,
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
