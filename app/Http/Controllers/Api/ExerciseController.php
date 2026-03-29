<?php

namespace App\Http\Controllers\Api;

use App\Enums\ExerciseVisibility;
use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\Exercise;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $query = Exercise::query()
            ->where('is_active', true)
            ->orderBy('name');

        if (! $actor->isAdmin()) {
            $query->where(function ($builder) use ($actor): void {
                $builder->where('visibility', ExerciseVisibility::Shared->value);

                if ($actor->isTrainer()) {
                    $builder->orWhere('owner_id', $actor->id);
                }

                if ($actor->isClient() && $actor->trainer_id) {
                    $builder->orWhere('owner_id', $actor->trainer_id);
                }
            });
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer);

        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'muscle_group' => ['nullable', 'string', 'max:255'],
            'equipment' => ['nullable', 'string', 'max:255'],
            'movement_type' => ['nullable', 'string', 'max:255'],
            'default_unit' => ['nullable', 'string', 'max:50'],
            'visibility' => ['sometimes', Rule::enum(ExerciseVisibility::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $visibility = isset($fields['visibility'])
            ? ExerciseVisibility::from($fields['visibility'])
            : ExerciseVisibility::Shared;

        if ($actor->isTrainer()) {
            $visibility = ExerciseVisibility::Trainer;
            $fields['owner_id'] = $actor->id;
        } else {
            $fields['owner_id'] = $visibility === ExerciseVisibility::Shared ? null : $actor->id;
        }

        $fields['slug'] = Str::slug($fields['name']).'-'.Str::lower(Str::random(6));
        $fields['default_unit'] = $fields['default_unit'] ?? 'kg';
        $fields['visibility'] = $visibility->value;
        $fields['is_active'] = (bool) ($fields['is_active'] ?? true);

        $exercise = Exercise::create($fields);

        return response()->json([
            'data' => $exercise,
        ], 201);
    }

    public function update(Request $request, Exercise $exercise): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeExerciseManagement($actor, $exercise);

        $fields = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'muscle_group' => ['sometimes', 'nullable', 'string', 'max:255'],
            'equipment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'movement_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'default_unit' => ['sometimes', 'nullable', 'string', 'max:50'],
            'visibility' => ['sometimes', Rule::enum(ExerciseVisibility::class)],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($fields['visibility'])) {
            $visibility = ExerciseVisibility::from($fields['visibility']);
            $fields['visibility'] = $visibility->value;
            $fields['owner_id'] = $visibility === ExerciseVisibility::Shared ? null : $exercise->owner_id;
        }

        if (array_key_exists('name', $fields)) {
            $fields['slug'] = Str::slug($fields['name']).'-'.Str::lower(Str::random(6));
        }

        $exercise->fill($fields)->save();

        return response()->json([
            'data' => $exercise,
        ]);
    }

    public function destroy(Request $request, Exercise $exercise): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeExerciseManagement($actor, $exercise);

        $exercise->delete();

        return response()->json([
            'message' => 'Exercise deleted.',
        ]);
    }
}
