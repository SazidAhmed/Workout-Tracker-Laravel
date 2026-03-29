<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function forgotPassword(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'User with this email not found',
            ], 404);
        }

        if (! $user->is_active) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'You are not an active user. Cannot reset password',
            ], 401);
        }

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'status' => __($status),
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'User with this email not found',
            ], 404);
        }

        if (! $user->is_active) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'You are not an active user. Cannot reset password',
            ], 401);
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request): void {
                $user->forceFill([
                    'password' => Hash::make($request->string('password')->toString()),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password reset successfully',
            ]);
        }

        return response()->json([
            'message' => __($status),
        ], 500);
    }
}
