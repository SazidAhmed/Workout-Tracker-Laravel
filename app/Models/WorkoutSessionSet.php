<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutSessionSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'workout_session_exercise_id',
        'set_number',
        'is_warmup',
        'planned_reps_min',
        'planned_reps_max',
        'planned_weight',
        'actual_reps',
        'actual_weight',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_warmup' => 'boolean',
            'planned_weight' => 'decimal:2',
            'actual_weight' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function workoutSessionExercise(): BelongsTo
    {
        return $this->belongsTo(WorkoutSessionExercise::class);
    }
}
