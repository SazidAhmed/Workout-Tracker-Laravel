<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\BodyMetric;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BodyMetricController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $client = $actor->isClient() ? $actor : User::findOrFail($request->integer('client_id'));
        $this->authorizeClientView($actor, $client);

        return response()->json([
            'data' => BodyMetric::query()
                ->where('client_id', $client->id)
                ->orderByDesc('recorded_on')
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer, UserRole::Client);

        $fields = $request->validate([
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'recorded_on' => ['required', 'date'],
            'body_weight' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $client = $actor->isClient() ? $actor : User::findOrFail($fields['client_id'] ?? 0);
        $this->authorizeClientView($actor, $client);

        $metric = BodyMetric::create([
            'client_id' => $client->id,
            'recorded_on' => $fields['recorded_on'],
            'body_weight' => $fields['body_weight'],
            'notes' => $fields['notes'] ?? null,
        ]);

        return response()->json([
            'data' => $metric,
        ], 201);
    }

    public function update(Request $request, BodyMetric $bodyMetric): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeClientView($actor, $bodyMetric->client);

        $fields = $request->validate([
            'recorded_on' => ['sometimes', 'required', 'date'],
            'body_weight' => ['sometimes', 'required', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        $bodyMetric->fill($fields)->save();

        return response()->json([
            'data' => $bodyMetric,
        ]);
    }

    public function destroy(Request $request, BodyMetric $bodyMetric): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeClientView($actor, $bodyMetric->client);
        $bodyMetric->delete();

        return response()->json([
            'message' => 'Body metric deleted.',
        ]);
    }
}
