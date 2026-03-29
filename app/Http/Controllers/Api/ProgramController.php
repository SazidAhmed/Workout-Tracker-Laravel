<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProgramStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProgramController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $query = Program::query()
            ->with(['trainer:id,name', 'client:id,name', 'days.exercises'])
            ->latest();

        if ($actor->isTrainer()) {
            $query->where('trainer_id', $actor->id);
        }

        if ($actor->isClient()) {
            $query->where('client_id', $actor->id);
        }

        if ($clientId = $request->integer('client_id')) {
            if ($actor->isTrainer()) {
                $client = User::findOrFail($clientId);
                $this->authorizeClientView($actor, $client);
            }

            $query->where('client_id', $clientId);
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer);

        $payload = $this->validatePayload($request);

        $client = User::findOrFail($payload['client_id']);
        $this->authorizeClientManagement($actor, $client);

        $trainerId = $actor->isTrainer() ? $actor->id : ($payload['trainer_id'] ?? $client->trainer_id);

        $program = DB::transaction(function () use ($payload, $trainerId): Program {
            $program = Program::create([
                'trainer_id' => $trainerId,
                'client_id' => $payload['client_id'],
                'name' => $payload['name'],
                'goal' => $payload['goal'] ?? null,
                'status' => $payload['status'] ?? ProgramStatus::Draft->value,
                'start_date' => $payload['start_date'] ?? null,
                'end_date' => $payload['end_date'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ]);

            $this->syncDays($program, $payload['days'] ?? []);

            return $program->load(['trainer:id,name', 'client:id,name', 'days.exercises']);
        });

        return response()->json([
            'data' => $program,
        ], 201);
    }

    public function show(Request $request, Program $program): JsonResponse
    {
        $this->authorizeProgramAccess($request->user(), $program);

        return response()->json([
            'data' => $program->load(['trainer:id,name', 'client:id,name', 'days.exercises']),
        ]);
    }

    public function update(Request $request, Program $program): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeProgramAccess($actor, $program);

        if ($actor->isClient()) {
            return response()->json([
                'message' => 'Clients cannot edit programs.',
            ], 403);
        }

        $payload = $this->validatePayload($request, $program);

        $client = User::findOrFail($payload['client_id'] ?? $program->client_id);
        $this->authorizeClientManagement($actor, $client);

        $updated = DB::transaction(function () use ($program, $payload, $actor, $client): Program {
            $program->update([
                'trainer_id' => $actor->isTrainer() ? $actor->id : ($payload['trainer_id'] ?? $program->trainer_id ?? $client->trainer_id),
                'client_id' => $payload['client_id'] ?? $program->client_id,
                'name' => $payload['name'] ?? $program->name,
                'goal' => array_key_exists('goal', $payload) ? $payload['goal'] : $program->goal,
                'status' => $payload['status'] ?? $program->status->value,
                'start_date' => $payload['start_date'] ?? $program->start_date,
                'end_date' => $payload['end_date'] ?? $program->end_date,
                'notes' => array_key_exists('notes', $payload) ? $payload['notes'] : $program->notes,
            ]);

            if (array_key_exists('days', $payload)) {
                $program->days()->delete();
                $this->syncDays($program, $payload['days']);
            }

            return $program->load(['trainer:id,name', 'client:id,name', 'days.exercises']);
        });

        return response()->json([
            'data' => $updated,
        ]);
    }

    public function destroy(Request $request, Program $program): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeProgramAccess($actor, $program);

        if ($actor->isClient()) {
            return response()->json([
                'message' => 'Clients cannot delete programs.',
            ], 403);
        }

        $program->delete();

        return response()->json([
            'message' => 'Program deleted.',
        ]);
    }

    private function validatePayload(Request $request, ?Program $program = null): array
    {
        return $request->validate([
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'client_id' => [$program ? 'sometimes' : 'required', 'integer', 'exists:users,id'],
            'name' => [$program ? 'sometimes' : 'required', 'string', 'max:255'],
            'goal' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProgramStatus::class)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'days' => ['sometimes', 'array'],
            'days.*.title' => ['required', 'string', 'max:255'],
            'days.*.scheduled_on' => ['required', 'date'],
            'days.*.notes' => ['nullable', 'string'],
            'days.*.exercises' => ['required', 'array', 'min:1'],
            'days.*.exercises.*.exercise_id' => ['nullable', 'integer', 'exists:exercises,id'],
            'days.*.exercises.*.exercise_name' => ['required', 'string', 'max:255'],
            'days.*.exercises.*.target_sets' => ['nullable', 'integer', 'min:1'],
            'days.*.exercises.*.target_reps_min' => ['nullable', 'integer', 'min:1'],
            'days.*.exercises.*.target_reps_max' => ['nullable', 'integer', 'min:1'],
            'days.*.exercises.*.target_weight' => ['nullable', 'numeric', 'min:0'],
            'days.*.exercises.*.rest_seconds' => ['nullable', 'integer', 'min:0'],
            'days.*.exercises.*.notes' => ['nullable', 'string'],
        ]);
    }

    private function syncDays(Program $program, array $days): void
    {
        foreach ($days as $index => $dayData) {
            $day = $program->days()->create([
                'title' => $dayData['title'],
                'scheduled_on' => $dayData['scheduled_on'],
                'notes' => $dayData['notes'] ?? null,
            ]);

            foreach ($dayData['exercises'] as $exerciseIndex => $exerciseData) {
                $day->exercises()->create([
                    'exercise_id' => $exerciseData['exercise_id'] ?? null,
                    'exercise_name' => $exerciseData['exercise_name'],
                    'order_index' => $exerciseIndex,
                    'target_sets' => $exerciseData['target_sets'] ?? null,
                    'target_reps_min' => $exerciseData['target_reps_min'] ?? null,
                    'target_reps_max' => $exerciseData['target_reps_max'] ?? null,
                    'target_weight' => $exerciseData['target_weight'] ?? null,
                    'rest_seconds' => $exerciseData['rest_seconds'] ?? null,
                    'notes' => $exerciseData['notes'] ?? null,
                ]);
            }
        }
    }
}
