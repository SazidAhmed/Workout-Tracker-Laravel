<?php

use App\Enums\WorkoutSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_day_id')->nullable()->constrained('program_days')->nullOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('logged_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('last_edited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('status')->default(WorkoutSessionStatus::InProgress->value);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_session_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_id')->constrained('workout_sessions')->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->string('exercise_name');
            $table->unsignedInteger('order_index')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('workout_session_sets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workout_session_exercise_id')->constrained('workout_session_exercises')->cascadeOnDelete();
            $table->unsignedInteger('set_number');
            $table->boolean('is_warmup')->default(false);
            $table->unsignedInteger('planned_reps_min')->nullable();
            $table->unsignedInteger('planned_reps_max')->nullable();
            $table->decimal('planned_weight', 8, 2)->nullable();
            $table->unsignedInteger('actual_reps')->nullable();
            $table->decimal('actual_weight', 8, 2)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_session_sets');
        Schema::dropIfExists('workout_session_exercises');
        Schema::dropIfExists('workout_sessions');
    }
};
