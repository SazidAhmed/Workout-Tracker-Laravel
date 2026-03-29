<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registration(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'Registration successful',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $fields = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $fields['email'])->first();

        if (! $user) {
            return response()->json([
                'message' => 'Email not found',
                'user' => $user,
            ], 401);
        }

        if (! Hash::check($fields['password'], $user->password)) {
            return response()->json([
                'message' => 'Email or password did not match',
            ], 401);
        }

        if (! $user->is_active) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'You are not an active user - contact your admin',
            ], 401);
        }

        $token = $user->createToken('myapptoken')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'is_admin' => (bool) $user->is_admin,
            'message' => 'You are logged in successfully',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logged out',
        ]);
    }
}
