<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\UserRegistered;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationNotification
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(UserRegistered $event): void
    {
        // if (!$event->user->hasVerifiedEmail()) {
        //     $event->user->sendEmailVerificationNotification();
        // }

        Log::info('User registered event processed', [
            'user_id' => $event->user->id,
            'email' => $event->user->email
        ]);
    }
}
