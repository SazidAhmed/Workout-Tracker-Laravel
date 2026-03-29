<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function list(Request $request): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $users = User::query()->latest('id')->get();

        return response()->json([
            'message' => 'Data found',
            'data' => $users,
        ]);
    }

    public function getById(Request $request, int $id): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::withTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Data found',
            'data' => $user,
        ]);
    }

    public function create(Request $request): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'password' => Hash::make($fields['password']),
            'phone' => $fields['phone'] ?? null,
            'address' => $fields['address'] ?? null,
            'is_active' => (bool) ($fields['is_active'] ?? true),
            'is_admin' => (bool) ($fields['is_admin'] ?? false),
            'is_trashed' => false,
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'Registration successful',
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::withTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $fields = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'is_admin' => ['sometimes', 'boolean'],
            'password' => ['sometimes', 'required', 'string', 'confirmed'],
        ]);

        if (array_key_exists('name', $fields)) {
            $user->name = $fields['name'];
        }

        if (array_key_exists('email', $fields) && $fields['email'] !== $user->email) {
            $user->tokens()->delete();
            $user->email = $fields['email'];
        }

        if (array_key_exists('phone', $fields)) {
            $user->phone = $fields['phone'];
        }

        if (array_key_exists('address', $fields)) {
            $user->address = $fields['address'];
        }

        if (array_key_exists('is_active', $fields)) {
            $user->is_active = (bool) $fields['is_active'];

            if (! $user->is_active) {
                $user->tokens()->delete();
            }
        }

        if (array_key_exists('is_admin', $fields)) {
            $user->is_admin = (bool) $fields['is_admin'];
        }

        if (array_key_exists('password', $fields)) {
            $user->password = Hash::make($fields['password']);
            $user->tokens()->delete();
        }

        $user->save();

        return response()->json([
            'message' => 'Data updated',
            'data' => $user,
        ]);
    }

    public function delete(Request $request, int $id): JsonResponse
    {
        if (in_array($id, [1], true)) {
            return response()->json([
                'message' => 'Cannot delete system admin',
            ], 403);
        }

        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $user->tokens()->delete();
        $user->is_trashed = true;
        $user->save();
        $user->delete();

        return response()->json([
            'message' => 'Data moved to recycle bin',
        ]);
    }

    public function deletePermanently(Request $request, int $id): JsonResponse
    {
        if (in_array($id, [1], true)) {
            return response()->json([
                'message' => 'Cannot delete system admin',
            ], 403);
        }

        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::withTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'Not found',
            ], 404);
        }

        $user->tokens()->delete();
        $user->forceDelete();

        return response()->json([
            'message' => 'Data deleted permanently',
        ]);
    }

    public function sendOtp(Request $request, int $id): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $tempPassword = Str::random(8);

        $user->password = Hash::make($tempPassword);
        $user->tokens()->delete();
        $user->save();

        try {
            Mail::raw(
                "Hi {$user->name},\n\nYour temporary password is: {$tempPassword}\n\nPlease login and change your password.",
                function ($message) use ($user): void {
                    $message->to($user->email)
                        ->subject('Your Temporary Password');
                }
            );

            return response()->json([
                'message' => 'Temporary password sent successfully to '.$user->email,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Failed to send temporary password: '.$e->getMessage(),
            ], 500);
        }
    }

    public function deletedList(Request $request): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $users = User::onlyTrashed()->latest('id')->get();

        return response()->json([
            'message' => 'Data found',
            'data' => $users,
        ]);
    }

    public function restore(Request $request, int $id): JsonResponse
    {
        if (! $this->isActiveRequester($request)) {
            return response()->json([
                'message' => 'You are not an active user. Cannot perform this action',
            ], 401);
        }

        $user = User::onlyTrashed()->find($id);

        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 404);
        }

        $user->restore();
        $user->is_trashed = false;
        $user->save();

        return response()->json([
            'message' => 'User restored successfully',
            'data' => $user,
        ]);
    }

    private function isActiveRequester(Request $request): bool
    {
        $requestUser = $request->user();

        return (bool) ($requestUser && $requestUser->is_active);
    }
}
