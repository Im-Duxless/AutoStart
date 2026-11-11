<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'role' => UserRole::Student,
            'phone' => null,
            'password' => static::$password ??= Hash::make('AutoStart!2026'),
            'remember_token' => Str::random(10),
        ];
    }

    public function instructor(): static
    {
        return $this->state(['role' => UserRole::Instructor]);
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin]);
    }
}
