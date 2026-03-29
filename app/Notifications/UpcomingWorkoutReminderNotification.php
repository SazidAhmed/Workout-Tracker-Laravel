<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class UpcomingWorkoutReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $dedupeKey,
        private readonly string $title,
        private readonly string $message,
        private readonly string $scheduledOn,
        private readonly string $programName,
        private readonly ?string $clientName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'upcoming_workout',
            'title' => $this->title,
            'message' => $this->message,
            'scheduled_on' => $this->scheduledOn,
            'program_name' => $this->programName,
            'client_name' => $this->clientName,
            'action_url' => '/app/upcoming',
            'dedupe_key' => $this->dedupeKey,
        ];
    }
}
