<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\WorkoutSessionStatus;
use App\Models\BodyMetric;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionExercise;
use App\Models\WorkoutSessionSet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_progress_metrics(): void
    {
        $trainer = User::factory()->trainer()->create();
        $client = User::factory()->client($trainer)->create();

        $session = WorkoutSession::create([
            'client_id' => $client->id,
            'trainer_id' => $trainer->id,
            'logged_by_user_id' => $client->id,
            'last_edited_by_user_id' => $client->id,
            'title' => 'Push Day',
            'status' => WorkoutSessionStatus::Completed,
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
        ]);

        $exercise = WorkoutSessionExercise::create([
            'workout_session_id' => $session->id,
            'exercise_name' => 'Bench Press',
            'order_index' => 0,
        ]);

        WorkoutSessionSet::create([
            'workout_session_exercise_id' => $exercise->id,
            'set_number' => 1,
            'actual_reps' => 5,
            'actual_weight' => 100,
            'completed_at' => now()->subDay(),
        ]);

        BodyMetric::create([
            'client_id' => $client->id,
            'recorded_on' => now()->toDateString(),
            'body_weight' => 80,
        ]);

        Sanctum::actingAs($trainer);

        $response = $this->getJson('/api/dashboard/summary?client_id='.$client->id);

        $response->assertOk()
            ->assertJsonPath('data.heaviest_set', 100)
            ->assertJsonPath('data.total_volume', 500)
            ->assertJsonPath('data.completion_rate', 100)
            ->assertJsonCount(1, 'data.body_weight_trend');
    }
}
