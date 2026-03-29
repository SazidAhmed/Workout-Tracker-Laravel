<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GymInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly User $invitedBy,
        private readonly string $plainToken,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $baseUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $activationUrl = $baseUrl.'/auth/activate-invite?token='.$this->plainToken;

        return (new MailMessage)
            ->subject('Your gym account invitation')
            ->greeting('You have been invited to the workout tracker')
            ->line("{$this->invitedBy->name} created an account for you.")
            ->line('Activate your account, set your password, and verify your email to start using the app.')
            ->action('Activate account', $activationUrl)
            ->line('This invitation expires in 7 days.');
    }
}
