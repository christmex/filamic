<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\MonthEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'school_id' => School::factory(),
            'classroom_id' => Classroom::factory(),
            'school_year_id' => SchoolYear::factory()->inactive(),
            'student_id' => Student::factory(),

            'branch_name' => fn (array $attributes) => Branch::find($attributes['branch_id'])?->name ?? fake()->company(),
            'school_name' => fn (array $attributes) => School::find($attributes['school_id'])?->name ?? fake()->company(),
            'classroom_name' => fn (array $attributes) => Classroom::find($attributes['classroom_id'])?->name ?? fake()->word(),
            'school_year_name' => fn (array $attributes) => SchoolYear::find($attributes['school_year_id'])?->name ?? '2024/2025',
            'student_name' => fn (array $attributes) => Student::find($attributes['student_id'])?->name ?? fake()->name(),

            'type' => fake()->randomElement(InvoiceTypeEnum::cases()),
            'month' => fake()->randomElement(MonthEnum::cases()),
            'virtual_account_number' => function (array $attributes): string {
                $type = $attributes['type'] ?? InvoiceTypeEnum::MONTHLY_FEE;
                $type = $type instanceof InvoiceTypeEnum ? $type : InvoiceTypeEnum::from((int) $type);

                $student = Student::find($attributes['student_id']);

                $virtualAccountNumber = match ($type) {
                    InvoiceTypeEnum::BOOK_FEE => $student?->book_fee_virtual_account,
                    default => $student?->monthly_fee_virtual_account,
                };

                return $virtualAccountNumber ?? fake()->numerify('########');
            },

            'amount' => fake()->numberBetween(50_000, 500_000),
            'fine' => 0,
            'discount' => 0,
            'total_amount' => fn (array $attributes) => $attributes['amount'],

            'issued_at' => now(),
            'due_date' => now()->addDays(30),

            'status' => InvoiceStatusEnum::UNPAID,
            'payment_method' => null,
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state([
            'status' => InvoiceStatusEnum::PAID,
            'paid_at' => now(),
        ]);
    }

    public function unpaid(): static
    {
        return $this->state([
            'status' => InvoiceStatusEnum::UNPAID,
            'paid_at' => null,
        ]);
    }

    public function monthlyFee(): static
    {
        return $this->state([
            'type' => InvoiceTypeEnum::MONTHLY_FEE,
        ]);
    }

    public function bookFee(): static
    {
        return $this->state([
            'type' => InvoiceTypeEnum::BOOK_FEE,
        ]);
    }
}
