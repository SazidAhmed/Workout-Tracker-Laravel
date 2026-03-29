<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SettingController extends Controller
{
    public function profilePasswordUpdate(Request $request): JsonResponse
    {
        $requestUser = $request->user();

        if (! $requestUser || ! $requestUser->is_active) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $fields = $request->validate([
            'current_password' => ['required', 'current_password:sanctum'],
            'new_password' => ['required', 'string', 'min:8'],
            'new_confirm_password' => ['required', 'same:new_password'],
        ]);

        $user = User::find($requestUser->id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user->password = Hash::make($fields['new_password']);
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
