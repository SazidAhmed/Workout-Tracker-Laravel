<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramDayExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_day_id',
        'exercise_id',
        'exercise_name',
        'order_index',
        'target_sets',
        'target_reps_min',
        'target_reps_max',
        'target_weight',
        'rest_seconds',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_weight' => 'decimal:2',
        ];
    }

    public function programDay(): BelongsTo
    {
        return $this->belongsTo(ProgramDay::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }
}
