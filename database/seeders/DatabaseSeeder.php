<?php

namespace Database\Seeders;

use App\Enums\ExerciseVisibility;
use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        ], [
            'name' => env('ADMIN_NAME', 'Gym Admin'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'role' => UserRole::Admin,
            'is_admin' => true,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        collect([
            ['name' => 'Barbell Back Squat', 'muscle_group' => 'Legs', 'equipment' => 'Barbell'],
            ['name' => 'Barbell Bench Press', 'muscle_group' => 'Chest', 'equipment' => 'Barbell'],
            ['name' => 'Deadlift', 'muscle_group' => 'Posterior Chain', 'equipment' => 'Barbell'],
            ['name' => 'Pull-Up', 'muscle_group' => 'Back', 'equipment' => 'Bodyweight'],
            ['name' => 'Dumbbell Shoulder Press', 'muscle_group' => 'Shoulders', 'equipment' => 'Dumbbell'],
        ])->each(function (array $exercise): void {
            Exercise::firstOrCreate([
                'slug' => Str::slug($exercise['name']),
            ], [
                ...$exercise,
                'default_unit' => 'kg',
                'visibility' => ExerciseVisibility::Shared,
                'is_active' => true,
            ]);
        });
    }
}
