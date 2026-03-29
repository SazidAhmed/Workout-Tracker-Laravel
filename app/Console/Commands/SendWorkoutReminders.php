<?php

namespace App\Console\Commands;

use App\Enums\ProgramStatus;
use App\Enums\WorkoutSessionStatus;
use App\Models\ProgramDay;
use App\Models\User;
use App\Notifications\MissedWorkoutReminderNotification;
use App\Notifications\UpcomingWorkoutReminderNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SendWorkoutReminders extends Command
{
    protected $signature = 'fit:send-workout-reminders {--date= : Reference date in YYYY-MM-DD format}';

    protected $description = 'Send in-app reminders for upcoming and missed workouts.';

    public function handle(): int
    {
        $referenceDate = $this->resolveReferenceDate();
        if (! $referenceDate) {
            return self::FAILURE;
        }

        $upcomingDate = $referenceDate->addDay()->toDateString();
        $missedDate = $referenceDate->subDay()->toDateString();

        $upcomingSent = $this->sendUpcomingReminders($upcomingDate);
        $missedSent = $this->sendMissedReminders($missedDate);

        $this->info("Upcoming reminders sent: {$upcomingSent}");
        $this->info("Missed reminders sent: {$missedSent}");

        return self::SUCCESS;
    }

    private function resolveReferenceDate(): ?CarbonImmutable
    {
        $dateInput = (string) ($this->option('date') ?? '');

        if ($dateInput === '') {
            return CarbonImmutable::today();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $dateInput)->startOfDay();
        } catch (\Throwable) {
            $this->error('Invalid --date format. Use YYYY-MM-DD.');

            return null;
        }
    }

    private function sendUpcomingReminders(string $upcomingDate): int
    {
        $sent = 0;

        $days = ProgramDay::query()
            ->with(['program.client', 'program.trainer'])
            ->whereDate('scheduled_on', $upcomingDate)
            ->whereHas('program', fn ($builder) => $builder->where('status', ProgramStatus::Active->value))
            ->get();

        foreach ($days as $day) {
            $program = $day->program;
            if (! $program) {
                continue;
            }

            $client = $program->client;
            $trainer = $program->trainer;
            $formattedDate = CarbonImmutable::parse($day->scheduled_on)->toFormattedDateString();

            if ($client && $client->is_active) {
                $dedupeKey = "upcoming:client:{$client->id}:day:{$day->id}:date:{$upcomingDate}";
                if (! $this->notificationExists($client, UpcomingWorkoutReminderNotification::class, $dedupeKey)) {
                    $client->notify(new UpcomingWorkoutReminderNotification(
                        dedupeKey: $dedupeKey,
                        title: 'Upcoming workout reminder',
                        message: "You have \"{$day->title}\" scheduled for {$formattedDate}.",
                        scheduledOn: $upcomingDate,
                        programName: $program->name,
                        clientName: $client->name,
                    ));
                    $sent++;
                }
            }

            if ($trainer && $trainer->is_active) {
                $dedupeKey = "upcoming:trainer:{$trainer->id}:day:{$day->id}:date:{$upcomingDate}";
                if (! $this->notificationExists($trainer, UpcomingWorkoutReminderNotification::class, $dedupeKey)) {
                    $trainer->notify(new UpcomingWorkoutReminderNotification(
                        dedupeKey: $dedupeKey,
                        title: 'Client workout scheduled',
                        message: "{$client?->name} has \"{$day->title}\" scheduled for {$formattedDate}.",
                        scheduledOn: $upcomingDate,
                        programName: $program->name,
                        clientName: $client?->name,
                    ));
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function sendMissedReminders(string $missedDate): int
    {
        $sent = 0;

        $days = ProgramDay::query()
            ->with(['program.client', 'program.trainer'])
            ->whereDate('scheduled_on', $missedDate)
            ->whereHas('program', fn ($builder) => $builder->where('status', ProgramStatus::Active->value))
            ->whereDoesntHave('workoutSessions', fn ($builder) => $builder->where('status', WorkoutSessionStatus::Completed->value))
            ->get();

        foreach ($days as $day) {
            $program = $day->program;
            if (! $program) {
                continue;
            }

            $client = $program->client;
            $trainer = $program->trainer;
            $formattedDate = CarbonImmutable::parse($day->scheduled_on)->toFormattedDateString();

            if ($client && $client->is_active) {
                $dedupeKey = "missed:client:{$client->id}:day:{$day->id}:date:{$missedDate}";
                if (! $this->notificationExists($client, MissedWorkoutReminderNotification::class, $dedupeKey)) {
                    $client->notify(new MissedWorkoutReminderNotification(
                        dedupeKey: $dedupeKey,
                        title: 'Missed workout follow-up',
                        message: "\"{$day->title}\" from {$formattedDate} is still incomplete.",
                        scheduledOn: $missedDate,
                        programName: $program->name,
                        clientName: $client->name,
                    ));
                    $sent++;
                }
            }

            if ($trainer && $trainer->is_active) {
                $dedupeKey = "missed:trainer:{$trainer->id}:day:{$day->id}:date:{$missedDate}";
                if (! $this->notificationExists($trainer, MissedWorkoutReminderNotification::class, $dedupeKey)) {
                    $trainer->notify(new MissedWorkoutReminderNotification(
                        dedupeKey: $dedupeKey,
                        title: 'Client missed workout',
                        message: "{$client?->name} has not completed \"{$day->title}\" scheduled on {$formattedDate}.",
                        scheduledOn: $missedDate,
                        programName: $program->name,
                        clientName: $client?->name,
                    ));
                    $sent++;
                }
            }
        }

        return $sent;
    }

    private function notificationExists(User $user, string $type, string $dedupeKey): bool
    {
        return $user->notifications()
            ->where('type', $type)
            ->where('data->dedupe_key', $dedupeKey)
            ->exists();
    }
}
