<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserTypeEnum;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'user_type' => fake()->randomElement(UserTypeEnum::cases())->value,
            'remember_token' => Str::random(10),
        ];
    }

    public function employee(): static
    {
        return $this->state([
            'user_type' => UserTypeEnum::EMPLOYEE,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'is_active' => true,
        ]);
    }

    public function unverified(): static
    {
        return $this->state([
            'email_verified_at' => null,
        ]);
    }
}
