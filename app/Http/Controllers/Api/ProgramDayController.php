<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\ProgramDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgramDayController extends Controller
{
    use InteractsWithGymPermissions;

    public function show(Request $request, ProgramDay $programDay): JsonResponse
    {
        $this->authorizeProgramAccess($request->user(), $programDay->program);

        return response()->json([
            'data' => $programDay->load(['program.client:id,name', 'program.trainer:id,name', 'exercises']),
        ]);
    }

    public function destroy(Request $request, ProgramDay $programDay): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeProgramAccess($actor, $programDay->program);

        if ($actor->role === UserRole::Client) {
            return response()->json([
                'message' => 'Clients cannot delete scheduled days.',
            ], 403);
        }

        $programDay->delete();

        return response()->json([
            'message' => 'Program day deleted.',
        ]);
    }
}
