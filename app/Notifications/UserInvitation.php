<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Config;

class UserInvitation extends Notification
{
    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $minutes = Config::get('auth.passwords.'.Config::get('fortify.passwords').'.expire');

        return (new MailMessage)
            ->subject('You have been invited to '.config('app.name'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('An administrator created an account for you. Set a password to get started.')
            ->action('Set your password', $url)
            ->line("This link expires in {$minutes} minutes.");
    }
}
