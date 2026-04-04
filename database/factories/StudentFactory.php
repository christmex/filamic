<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GenderEnum;
use App\Enums\ReligionEnum;
use App\Enums\StatusInFamilyEnum;
use Database\Factories\Traits\HasActiveState;
use Database\Factories\Traits\ResolveBranch;
use Database\Factories\Traits\ResolvesSchool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Student>
 */
class StudentFactory extends Factory
{
    use HasActiveState;
    use ResolveBranch;
    use ResolvesSchool;

    public function configure(): self
    {
        return parent::configure()
            ->forSchool()
            ->forBranch();
    }

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'gender' => fake()->randomElement(GenderEnum::cases()),
            'status_in_family' => fake()->randomElement(StatusInFamilyEnum::cases()),
            'religion' => fake()->randomElement(ReligionEnum::cases()),
            'is_active' => fake()->boolean(),
            'birth_place' => $this->faker->city(),
            'birth_date' => $this->faker->date(),
            'nisn' => $this->faker->unique()->numerify('##########'),
            'nis' => $this->faker->numerify('#####'),

            'monthly_fee_virtual_account' => fake()->unique()->numerify('########'),
            'book_fee_virtual_account' => fake()->unique()->numerify('########'),
            'monthly_fee_amount' => fake()->numberBetween(100_000, 500_000),
            'book_fee_amount' => fake()->numberBetween(50_000, 200_000),
        ];
    }

    public function withoutMonthlyFee(): static
    {
        return $this->state([
            'monthly_fee_virtual_account' => null,
            'monthly_fee_amount' => 0,
        ]);
    }

    public function withoutBookFee(): static
    {
        return $this->state([
            'book_fee_virtual_account' => null,
            'book_fee_amount' => 0,
        ]);
    }
}
