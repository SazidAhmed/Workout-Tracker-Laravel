<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BodyMetricController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExerciseController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgramDayController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkoutSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/invitations/{token}', [InvitationController::class, 'show']);
    Route::post('/invitations/{token}/activate', [InvitationController::class, 'activate']);
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
            ->name('verification.send');
    });
});

Route::middleware(['auth:sanctum', 'verified'])->group(function (): void {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::get('/clients', [UserController::class, 'clients']);
    Route::get('/trainers', [UserController::class, 'trainers']);

    Route::get('/exercises', [ExerciseController::class, 'index']);
    Route::post('/exercises', [ExerciseController::class, 'store']);
    Route::put('/exercises/{exercise}', [ExerciseController::class, 'update']);
    Route::delete('/exercises/{exercise}', [ExerciseController::class, 'destroy']);

    Route::get('/programs', [ProgramController::class, 'index']);
    Route::post('/programs', [ProgramController::class, 'store']);
    Route::get('/programs/{program}', [ProgramController::class, 'show']);
    Route::put('/programs/{program}', [ProgramController::class, 'update']);
    Route::delete('/programs/{program}', [ProgramController::class, 'destroy']);

    Route::get('/program-days/{programDay}', [ProgramDayController::class, 'show']);
    Route::delete('/program-days/{programDay}', [ProgramDayController::class, 'destroy']);

    Route::get('/workout-sessions', [WorkoutSessionController::class, 'index']);
    Route::post('/workout-sessions', [WorkoutSessionController::class, 'store']);
    Route::get('/workout-sessions/{workoutSession}', [WorkoutSessionController::class, 'show']);
    Route::put('/workout-sessions/{workoutSession}', [WorkoutSessionController::class, 'update']);
    Route::post('/workout-sessions/{workoutSession}/complete', [WorkoutSessionController::class, 'complete']);

    Route::get('/body-metrics', [BodyMetricController::class, 'index']);
    Route::post('/body-metrics', [BodyMetricController::class, 'store']);
    Route::put('/body-metrics/{bodyMetric}', [BodyMetricController::class, 'update']);
    Route::delete('/body-metrics/{bodyMetric}', [BodyMetricController::class, 'destroy']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/dashboard/exercise-progress', [DashboardController::class, 'exerciseProgress']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
});
