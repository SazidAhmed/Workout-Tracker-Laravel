<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ProgramDayExercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_snapshots_program_day_structure(): void
    {
        $trainer = User::factory()->trainer()->create();
        $client = User::factory()->client($trainer)->create();

        Sanctum::actingAs($trainer);

        $programResponse = $this->postJson('/api/programs', [
            'client_id' => $client->id,
            'name' => 'Strength Block',
            'status' => 'active',
            'days' => [
                [
                    'title' => 'Lower Body',
                    'scheduled_on' => now()->toDateString(),
                    'exercises' => [
                        [
                            'exercise_name' => 'Back Squat',
                            'target_sets' => 3,
                            'target_reps_min' => 5,
                            'target_reps_max' => 5,
                            'target_weight' => 100,
                        ],
                    ],
                ],
            ],
        ]);

        $programResponse->assertCreated();

        $programDayId = $programResponse->json('data.days.0.id');
        $exerciseId = $programResponse->json('data.days.0.exercises.0.id');

        Sanctum::actingAs($client);

        $sessionResponse = $this->postJson('/api/workout-sessions', [
            'program_day_id' => $programDayId,
        ]);

        $sessionResponse->assertCreated()
            ->assertJsonPath('data.exercises.0.sets.0.planned_weight', '100.00');

        ProgramDayExercise::findOrFail($exerciseId)->update([
            'target_weight' => 120,
        ]);

        $this->assertSame('100.00', $sessionResponse->json('data.exercises.0.sets.0.planned_weight'));
    }
}
