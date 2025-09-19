<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Jurist Verification Code')
            ->greeting("Hello {$notifiable->first_name}!")
            ->line('Thank you for registering. Please use the code below to verify your email address.')
            ->line('Your verification code is:')
            ->line(new \Illuminate\Support\HtmlString('<p style="font-size: 24px; font-weight: bold; text-align: center; letter-spacing: 5px;">'.$this->code.'</p>'))
            ->line('')
            ->line('This code will expire in 15 minutes.')
            ->line('If you did not create an account, no further action is required.')
            ->salutation('Best regards, The Jurist Team');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'email_verification',
            'code' => $this->code,
            'expires_at' => now()->addMinutes(15)->toISOString(),
        ];
    }
}
