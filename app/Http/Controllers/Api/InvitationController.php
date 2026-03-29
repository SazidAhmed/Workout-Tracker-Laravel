<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function show(string $token): JsonResponse
    {
        $user = $this->resolveInvitation($token);

        return response()->json([
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->value,
            ],
        ]);
    }

    public function activate(Request $request, string $token): JsonResponse
    {
        $user = $this->resolveInvitation($token);

        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->forceFill([
            'name' => $fields['name'],
            'password' => Hash::make($fields['password']),
            'invitation_token' => null,
            'invitation_accepted_at' => now(),
        ])->save();

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Invitation accepted. Please verify your email before using the app.',
        ]);
    }

    private function resolveInvitation(string $token): User
    {
        $user = User::where('invitation_token', hash('sha256', $token))
            ->whereNotNull('invitation_expires_at')
            ->where('invitation_expires_at', '>', now())
            ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'token' => ['This invitation is invalid or has expired.'],
            ]);
        }

        return $user;
    }
}
