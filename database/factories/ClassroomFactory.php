<?php

declare(strict_types=1);

namespace Database\Factories;

use Database\Factories\Traits\ResolvesSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Classroom>
 */
class ClassroomFactory extends Factory
{
    use ResolvesSchool;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->numerify('Classroom-####'),
            'grade' => fake()->numberBetween(1, 12),
            'identifier' => fake()->numberBetween(1, 12),
            'phase' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'is_moving_class' => fake()->boolean(),
        ];
    }

    public function forGrade(int $grade): static
    {
        return $this->state([
            'grade' => $grade,
        ]);
    }

    public function forPhase(string $phase): static
    {
        return $this->state([
            'phase' => $phase,
        ]);
    }

    public function setAsMovingClass(): static
    {
        return $this->state([
            'is_moving_class' => true,
        ]);
    }

    public function setAsRegularClass(): static
    {
        return $this->state([
            'is_moving_class' => false,
        ]);
    }
}
