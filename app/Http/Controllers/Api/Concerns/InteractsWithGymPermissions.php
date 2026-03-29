<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\Program;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Auth\Access\AuthorizationException;

trait InteractsWithGymPermissions
{
    protected function ensureActive(User $user): void
    {
        if (! $user->is_active) {
            throw new AuthorizationException('Inactive users cannot perform this action.');
        }
    }

    protected function ensureRole(User $user, UserRole ...$roles): void
    {
        $this->ensureActive($user);

        if (! in_array($user->role, $roles, true)) {
            throw new AuthorizationException('You are not allowed to perform this action.');
        }
    }

    protected function canManageClient(User $actor, User $client): bool
    {
        if (! $client->isClient()) {
            return false;
        }

        if ($actor->isAdmin()) {
            return true;
        }

        return $actor->isTrainer() && $client->trainer_id === $actor->id;
    }

    protected function canViewClient(User $actor, User $client): bool
    {
        if ($actor->id === $client->id) {
            return true;
        }

        return $this->canManageClient($actor, $client);
    }

    protected function authorizeClientView(User $actor, User $client): void
    {
        $this->ensureActive($actor);

        if (! $this->canViewClient($actor, $client)) {
            throw new AuthorizationException('You are not allowed to access this client.');
        }
    }

    protected function authorizeClientManagement(User $actor, User $client): void
    {
        $this->ensureActive($actor);

        if (! $this->canManageClient($actor, $client)) {
            throw new AuthorizationException('You are not allowed to manage this client.');
        }
    }

    protected function authorizeProgramAccess(User $actor, Program $program): void
    {
        $this->ensureActive($actor);

        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->isTrainer() && $program->trainer_id === $actor->id) {
            return;
        }

        if ($actor->isClient() && $program->client_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('You are not allowed to access this program.');
    }

    protected function authorizeSessionAccess(User $actor, WorkoutSession $session): void
    {
        $this->ensureActive($actor);

        if ($actor->isAdmin()) {
            return;
        }

        if ($actor->isTrainer() && $session->trainer_id === $actor->id) {
            return;
        }

        if ($actor->isClient() && $session->client_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('You are not allowed to access this workout session.');
    }

    protected function authorizeExerciseManagement(User $actor, Exercise $exercise): void
    {
        $this->ensureActive($actor);

        if ($actor->isAdmin()) {
            return;
        }

        if ($exercise->owner_id === null) {
            throw new AuthorizationException('Shared exercises can only be edited by admins.');
        }

        if ($actor->isTrainer() && $exercise->owner_id === $actor->id) {
            return;
        }

        throw new AuthorizationException('You are not allowed to manage this exercise.');
    }
}
