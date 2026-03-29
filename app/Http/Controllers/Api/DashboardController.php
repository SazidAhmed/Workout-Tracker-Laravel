<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\WorkoutSessionStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\BodyMetric;
use App\Models\User;
use App\Models\WorkoutSession;
use App\Models\WorkoutSessionSet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use InteractsWithGymPermissions;

    public function summary(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $client = $actor->isClient() ? $actor : User::findOrFail($request->integer('client_id'));
        $this->authorizeClientView($actor, $client);

        $sessionIds = WorkoutSession::query()
            ->where('client_id', $client->id)
            ->pluck('id');

        $sets = WorkoutSessionSet::query()
            ->selectRaw('actual_weight, actual_reps, (COALESCE(actual_weight, 0) * COALESCE(actual_reps, 0)) as volume')
            ->whereIn('workout_session_exercise_id', function ($query) use ($sessionIds): void {
                $query->select('id')
                    ->from('workout_session_exercises')
                    ->whereIn('workout_session_id', $sessionIds);
            })
            ->whereNotNull('actual_reps')
            ->get();

        $completedSessions = WorkoutSession::query()
            ->where('client_id', $client->id)
            ->where('status', WorkoutSessionStatus::Completed->value)
            ->count();

        $scheduledSessions = WorkoutSession::query()
            ->where('client_id', $client->id)
            ->count();

        $bodyTrend = BodyMetric::query()
            ->where('client_id', $client->id)
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'body_weight']);

        $recentSessions = WorkoutSession::query()
            ->where('client_id', $client->id)
            ->with(['programDay:id,title,scheduled_on'])
            ->latest('started_at')
            ->limit(5)
            ->get();

        $heaviestSet = $sets->sortByDesc('actual_weight')->first();
        $best1rm = $sets->map(fn ($set) => $set->actual_weight && $set->actual_reps
            ? round($set->actual_weight * (1 + ($set->actual_reps / 30)), 2)
            : 0)->max();

        return response()->json([
            'data' => [
                'heaviest_set' => $heaviestSet?->actual_weight,
                'estimated_one_rep_max' => $best1rm ?: null,
                'total_volume' => round($sets->sum('volume'), 2),
                'completion_rate' => $scheduledSessions > 0 ? round(($completedSessions / $scheduledSessions) * 100, 1) : 0,
                'recent_sessions' => $recentSessions,
                'body_weight_trend' => $bodyTrend,
            ],
        ]);
    }

    public function exerciseProgress(Request $request): JsonResponse
    {
        $actor = $request->user();
        $client = $actor->isClient() ? $actor : User::findOrFail($request->integer('client_id'));
        $this->authorizeClientView($actor, $client);

        $exerciseId = $request->validate([
            'exercise_id' => ['required', 'integer', 'exists:exercises,id'],
        ])['exercise_id'];

        $points = DB::table('workout_session_sets')
            ->join('workout_session_exercises', 'workout_session_sets.workout_session_exercise_id', '=', 'workout_session_exercises.id')
            ->join('workout_sessions', 'workout_session_exercises.workout_session_id', '=', 'workout_sessions.id')
            ->where('workout_sessions.client_id', $client->id)
            ->where('workout_session_exercises.exercise_id', $exerciseId)
            ->whereNotNull('workout_session_sets.actual_reps')
            ->whereNotNull('workout_session_sets.actual_weight')
            ->orderBy('workout_sessions.started_at')
            ->get([
                'workout_sessions.started_at',
                'workout_session_sets.actual_weight',
                'workout_session_sets.actual_reps',
            ])
            ->map(fn ($row) => [
                'date' => $row->started_at,
                'weight' => (float) $row->actual_weight,
                'reps' => (int) $row->actual_reps,
                'estimated_one_rep_max' => round(((float) $row->actual_weight) * (1 + (((int) $row->actual_reps) / 30)), 2),
            ]);

        return response()->json([
            'data' => $points,
        ]);
    }
}
