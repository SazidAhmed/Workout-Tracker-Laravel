<?php

use App\Enums\ProgramStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('goal')->nullable();
            $table->string('status')->default(ProgramStatus::Draft->value);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('program_days', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
            $table->string('title');
            $table->date('scheduled_on');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('program_day_exercises', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('program_day_id')->constrained('program_days')->cascadeOnDelete();
            $table->foreignId('exercise_id')->nullable()->constrained('exercises')->nullOnDelete();
            $table->string('exercise_name');
            $table->unsignedInteger('order_index')->default(0);
            $table->unsignedInteger('target_sets')->nullable();
            $table->unsignedInteger('target_reps_min')->nullable();
            $table->unsignedInteger('target_reps_max')->nullable();
            $table->decimal('target_weight', 8, 2)->nullable();
            $table->unsignedInteger('rest_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_day_exercises');
        Schema::dropIfExists('program_days');
        Schema::dropIfExists('programs');
    }
};
