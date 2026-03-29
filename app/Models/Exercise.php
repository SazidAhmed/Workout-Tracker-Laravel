<?php

namespace App\Models;

use App\Enums\ExerciseVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'muscle_group',
        'equipment',
        'movement_type',
        'default_unit',
        'visibility',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => ExerciseVisibility::class,
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function programDayExercises(): HasMany
    {
        return $this->hasMany(ProgramDayExercise::class);
    }

    public function workoutSessionExercises(): HasMany
    {
        return $this->hasMany(WorkoutSessionExercise::class);
    }
}
