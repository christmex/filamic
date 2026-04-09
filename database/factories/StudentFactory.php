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
            // monthly_fee_virtual_account, book_fee_virtual_account,
            // monthly_fee_amount, book_fee_amount removed from students table.
            // TODO: Restore payment account states when student_payment_accounts table is implemented.
        ];
    }

    // TODO: Restore when student_payment_accounts table is implemented.
    // public function withoutMonthlyFee(): static
    // {
    //     return $this->state([
    //         'monthly_fee_virtual_account' => null,
    //         'monthly_fee_amount' => 0,
    //     ]);
    // }

    // TODO: Restore when student_payment_accounts table is implemented.
    // public function withoutBookFee(): static
    // {
    //     return $this->state([
    //         'book_fee_virtual_account' => null,
    //         'book_fee_amount' => 0,
    //     ]);
    // }
}
