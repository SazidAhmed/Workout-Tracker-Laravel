<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Enums\WorkoutSessionStatus;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\ProgramDay;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkoutSessionController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $query = WorkoutSession::query()
            ->with(['client:id,name', 'trainer:id,name', 'programDay:id,program_id,title,scheduled_on', 'exercises.sets'])
            ->latest('started_at');

        if ($actor->isTrainer()) {
            $query->where('trainer_id', $actor->id);
        }

        if ($actor->isClient()) {
            $query->where('client_id', $actor->id);
        }

        if ($clientId = $request->integer('client_id')) {
            $client = User::findOrFail($clientId);
            $this->authorizeClientView($actor, $client);
            $query->where('client_id', $clientId);
        }

        if ($request->string('scope')->toString() === 'upcoming') {
            $query->whereHas('programDay', fn ($builder) => $builder->whereDate('scheduled_on', '>=', now()->toDateString()));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $fields = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'program_day_id' => ['nullable', 'integer', 'exists:program_days,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'started_at' => ['nullable', 'date'],
            'exercises' => ['nullable', 'array'],
            'exercises.*.exercise_id' => ['nullable', 'integer', 'exists:exercises,id'],
            'exercises.*.exercise_name' => ['required_with:exercises', 'string', 'max:255'],
            'exercises.*.notes' => ['nullable', 'string'],
            'exercises.*.sets' => ['required_with:exercises', 'array'],
            'exercises.*.sets.*.is_warmup' => ['sometimes', 'boolean'],
            'exercises.*.sets.*.planned_reps_min' => ['nullable', 'integer', 'min:1'],
            'exercises.*.sets.*.planned_reps_max' => ['nullable', 'integer', 'min:1'],
            'exercises.*.sets.*.planned_weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.actual_reps' => ['nullable', 'integer', 'min:1'],
            'exercises.*.sets.*.actual_weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.notes' => ['nullable', 'string'],
        ]);

        $client = $actor->isClient()
            ? $actor
            : User::findOrFail($fields['client_id'] ?? 0);

        $this->authorizeClientView($actor, $client);

        $programDay = isset($fields['program_day_id']) ? ProgramDay::with(['program', 'exercises'])->findOrFail($fields['program_day_id']) : null;

        if ($programDay) {
            $this->authorizeProgramAccess($actor, $programDay->program);
        }

        $session = DB::transaction(function () use ($fields, $actor, $client, $programDay): WorkoutSession {
            $session = WorkoutSession::create([
                'program_day_id' => $programDay?->id,
                'client_id' => $client->id,
                'trainer_id' => $client->trainer_id,
                'logged_by_user_id' => $actor->id,
                'last_edited_by_user_id' => $actor->id,
                'title' => $fields['title'] ?? $programDay?->title ?? 'Workout Session',
                'notes' => $fields['notes'] ?? null,
                'status' => WorkoutSessionStatus::InProgress,
                'started_at' => $fields['started_at'] ?? now(),
            ]);

            $exercisePayloads = $programDay
                ? $programDay->exercises->map(fn ($exercise) => [
                    'exercise_id' => $exercise->exercise_id,
                    'exercise_name' => $exercise->exercise_name,
                    'notes' => $exercise->notes,
                    'sets' => collect(range(1, max(1, $exercise->target_sets ?? 1)))->map(fn ($setNumber) => [
                        'planned_reps_min' => $exercise->target_reps_min,
                        'planned_reps_max' => $exercise->target_reps_max,
                        'planned_weight' => $exercise->target_weight,
                        'is_warmup' => false,
                    ])->all(),
                ])->all()
                : ($fields['exercises'] ?? []);

            foreach ($exercisePayloads as $exerciseIndex => $exerciseData) {
                $sessionExercise = $session->exercises()->create([
                    'exercise_id' => $exerciseData['exercise_id'] ?? null,
                    'exercise_name' => $exerciseData['exercise_name'],
                    'order_index' => $exerciseIndex,
                    'notes' => $exerciseData['notes'] ?? null,
                ]);

                foreach ($exerciseData['sets'] as $setIndex => $setData) {
                    $sessionExercise->sets()->create([
                        'set_number' => $setIndex + 1,
                        'is_warmup' => (bool) ($setData['is_warmup'] ?? false),
                        'planned_reps_min' => $setData['planned_reps_min'] ?? null,
                        'planned_reps_max' => $setData['planned_reps_max'] ?? null,
                        'planned_weight' => $setData['planned_weight'] ?? null,
                        'actual_reps' => $setData['actual_reps'] ?? null,
                        'actual_weight' => $setData['actual_weight'] ?? null,
                        'completed_at' => isset($setData['actual_reps']) || isset($setData['actual_weight']) ? now() : null,
                        'notes' => $setData['notes'] ?? null,
                    ]);
                }
            }

            return $session->load(['client:id,name', 'trainer:id,name', 'programDay', 'exercises.sets']);
        });

        return response()->json([
            'data' => $session,
        ], 201);
    }

    public function show(Request $request, WorkoutSession $workoutSession): JsonResponse
    {
        $this->authorizeSessionAccess($request->user(), $workoutSession);

        return response()->json([
            'data' => $workoutSession->load(['client:id,name', 'trainer:id,name', 'programDay', 'exercises.sets']),
        ]);
    }

    public function update(Request $request, WorkoutSession $workoutSession): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeSessionAccess($actor, $workoutSession);

        $fields = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'exercises' => ['sometimes', 'array'],
            'exercises.*.id' => ['required', 'integer', 'exists:workout_session_exercises,id'],
            'exercises.*.notes' => ['nullable', 'string'],
            'exercises.*.sets' => ['required', 'array'],
            'exercises.*.sets.*.id' => ['required', 'integer', 'exists:workout_session_sets,id'],
            'exercises.*.sets.*.actual_reps' => ['nullable', 'integer', 'min:1'],
            'exercises.*.sets.*.actual_weight' => ['nullable', 'numeric', 'min:0'],
            'exercises.*.sets.*.notes' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($fields, $actor, $workoutSession): void {
            $workoutSession->fill([
                'title' => $fields['title'] ?? $workoutSession->title,
                'notes' => array_key_exists('notes', $fields) ? $fields['notes'] : $workoutSession->notes,
                'last_edited_by_user_id' => $actor->id,
            ])->save();

            foreach ($fields['exercises'] ?? [] as $exerciseData) {
                $sessionExercise = $workoutSession->exercises()->findOrFail($exerciseData['id']);
                $sessionExercise->update([
                    'notes' => $exerciseData['notes'] ?? $sessionExercise->notes,
                ]);

                foreach ($exerciseData['sets'] as $setData) {
                    $sessionSet = $sessionExercise->sets()->findOrFail($setData['id']);
                    $sessionSet->update([
                        'actual_reps' => $setData['actual_reps'] ?? null,
                        'actual_weight' => $setData['actual_weight'] ?? null,
                        'notes' => $setData['notes'] ?? null,
                        'completed_at' => isset($setData['actual_reps']) || isset($setData['actual_weight']) ? now() : null,
                    ]);
                }
            }
        });

        return response()->json([
            'data' => $workoutSession->fresh(['client:id,name', 'trainer:id,name', 'programDay', 'exercises.sets']),
        ]);
    }

    public function complete(Request $request, WorkoutSession $workoutSession): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeSessionAccess($actor, $workoutSession);

        $workoutSession->update([
            'status' => WorkoutSessionStatus::Completed,
            'completed_at' => now(),
            'last_edited_by_user_id' => $actor->id,
        ]);

        return response()->json([
            'data' => $workoutSession->fresh(['client:id,name', 'trainer:id,name', 'programDay', 'exercises.sets']),
        ]);
    }
}
