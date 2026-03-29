<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Api\Concerns\InteractsWithGymPermissions;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\GymInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    use InteractsWithGymPermissions;

    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer);

        $query = User::query()
            ->with('trainer')
            ->orderBy('name');

        if ($actor->isTrainer()) {
            $query->where(function ($builder) use ($actor): void {
                $builder->where('id', $actor->id)
                    ->orWhere('trainer_id', $actor->id);
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->where('role', $role);
        }

        return response()->json([
            'data' => $query->get()->map(fn (User $user) => $this->serializeUser($user)),
        ]);
    }

    public function clients(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer);

        $query = User::query()
            ->where('role', UserRole::Client->value)
            ->with('trainer')
            ->orderBy('name');

        if ($actor->isTrainer()) {
            $query->where('trainer_id', $actor->id);
        }

        return response()->json([
            'data' => $query->get()->map(fn (User $user) => $this->serializeUser($user)),
        ]);
    }

    public function trainers(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin);

        return response()->json([
            'data' => User::query()
                ->where('role', UserRole::Trainer->value)
                ->orderBy('name')
                ->get()
                ->map(fn (User $user) => $this->serializeUser($user)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor = $request->user();
        $this->ensureRole($actor, UserRole::Admin, UserRole::Trainer);

        $fields = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'trainer_id' => ['nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $role = UserRole::from($fields['role']);
        $trainerId = $fields['trainer_id'] ?? null;

        if ($actor->isTrainer()) {
            $role = UserRole::Client;
            $trainerId = $actor->id;
        }

        if ($role === UserRole::Client && ! $trainerId) {
            $trainerId = $actor->isTrainer() ? $actor->id : null;
        }

        if ($role === UserRole::Client && ! $trainerId) {
            return response()->json([
                'message' => 'Clients must be assigned to a trainer.',
            ], 422);
        }

        if ($role !== UserRole::Client) {
            $trainerId = null;
        }

        $plainToken = Str::random(40);

        $user = User::create([
            'name' => $fields['name'],
            'email' => $fields['email'],
            'phone' => $fields['phone'] ?? null,
            'address' => $fields['address'] ?? null,
            'role' => $role,
            'trainer_id' => $trainerId,
            'is_active' => (bool) ($fields['is_active'] ?? true),
            'is_admin' => $role === UserRole::Admin,
            'is_trashed' => false,
            'password' => Hash::make(Str::random(32)),
            'invited_by_user_id' => $actor->id,
            'invitation_token' => hash('sha256', $plainToken),
            'invitation_expires_at' => now()->addDays(7),
            'invitation_accepted_at' => null,
        ]);

        $user->notify(new GymInvitationNotification($actor, $plainToken));

        return response()->json([
            'message' => 'User invited successfully.',
            'data' => $this->serializeUser($user->fresh('trainer')),
        ], 201);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeUserAccess($actor, $user);

        return response()->json([
            'data' => $this->serializeUser($user->load('trainer')),
        ]);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->authorizeUserManagement($actor, $user);

        $fields = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'trainer_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($actor->isTrainer()) {
            unset($fields['role'], $fields['trainer_id']);
        }

        if (isset($fields['role'])) {
            $role = UserRole::from($fields['role']);
            $fields['role'] = $role->value;
            $fields['is_admin'] = $role === UserRole::Admin;
        }

        $effectiveRole = isset($fields['role'])
            ? UserRole::from($fields['role'])
            : $user->role;

        if ($effectiveRole !== UserRole::Client) {
            $fields['trainer_id'] = null;
        }

        $user->fill($fields)->save();

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $this->serializeUser($user->fresh('trainer')),
        ]);
    }

    private function authorizeUserAccess(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            $this->ensureActive($actor);

            return;
        }

        if ($target->isClient()) {
            $this->authorizeClientView($actor, $target);

            return;
        }

        $this->ensureRole($actor, UserRole::Admin);
    }

    private function authorizeUserManagement(User $actor, User $target): void
    {
        if ($target->isClient()) {
            $this->authorizeClientManagement($actor, $target);

            return;
        }

        $this->ensureRole($actor, UserRole::Admin);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'address' => $user->address,
            'role' => $user->role?->value,
            'is_active' => (bool) $user->is_active,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'trainer' => $user->trainer ? [
                'id' => $user->trainer->id,
                'name' => $user->trainer->name,
            ] : null,
            'invitation_pending' => (bool) $user->invitation_token,
        ];
    }
}
