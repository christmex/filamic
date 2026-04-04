<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceTypeEnum;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateMonthlyFeeInvoice
{
    use AsAction;

    public function handle(Branch $branch, array $data): int
    {
        $validated = Validator::make($data, [
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'issued_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:issued_at'],
        ])->validate();

        $month = $validated['month'];
        $issuedAt = $validated['issued_at'];
        $dueDate = $validated['due_date'];

        /** @var Builder|Student $getStudentsQuery */
        // @phpstan-ignore-next-line
        $getStudentsQuery = $branch->students();

        $students = $getStudentsQuery
            ->active()
            ->whereHas('currentEnrollment')
            ->eligibleForMonthlyFee()
            ->whereDoesntHave('invoices', function ($query) use ($month) {
                /** @var Invoice $query */
                // @phpstan-ignore-next-line
                $query->monthlyFeeForThisSchoolYear(month: $month);
            })
            ->with([
                'school',
                'currentEnrollment.classroom',
                'currentEnrollment.schoolYear',
            ])
            ->get();

        if ($students->isEmpty()) {
            return 0;
        }

        $newInvoices = $students->map(function (Student $student) use ($month, $issuedAt, $dueDate, $branch) {
            $enrollment = $student->currentEnrollment;

            $prepareFingerprint = [
                'type' => InvoiceTypeEnum::MONTHLY_FEE->value,
                'student_id' => $student->getKey(),
                'school_year_id' => $enrollment->school_year_id,
                'month' => $month,
            ];

            $preparedData = [
                'fingerprint' => Invoice::generateFingerprint($prepareFingerprint),
                'reference_number' => Invoice::generateReferenceNumber(),

                'branch_id' => $branch->getKey(),
                'school_id' => $student->school_id,
                'student_id' => $student->getKey(),
                'classroom_id' => $enrollment->classroom_id,
                'school_year_id' => $enrollment->school_year_id,

                'branch_name' => $branch->name,
                'school_name' => $student->school->name,
                'classroom_name' => $enrollment->classroom->name,
                'school_year_name' => $enrollment->schoolYear->name,
                'student_name' => $student->name,

                'type' => InvoiceTypeEnum::MONTHLY_FEE,
                'month' => $month,

                'virtual_account_number' => $student->monthly_fee_virtual_account,
                'amount' => $student->monthly_fee_amount,
                'total_amount' => $student->monthly_fee_amount,

                'due_date' => $dueDate,
                'issued_at' => $issuedAt,
            ];

            return $preparedData;
        })->toArray();

        return DB::transaction(function () use ($newInvoices) {
            foreach (array_chunk($newInvoices, 500) as $chunk) {
                Invoice::fillAndInsert($chunk);
            }

            return count($newInvoices);
        });
    }
}
