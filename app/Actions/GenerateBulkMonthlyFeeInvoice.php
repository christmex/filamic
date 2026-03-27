<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceTypeEnum;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateBulkMonthlyFeeInvoice
{
    use AsAction;

    public function handle(array $data, Student $student): int
    {
        $validated = Validator::make($data, [
            'month' => ['required', 'array'],
            'month.*' => ['required', 'integer', 'min:1', 'max:12'],
            'issued_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:issued_at'],
        ])->validate();

        $issuedAt = $validated['issued_at'];
        $dueDate = $validated['due_date'];

        return DB::transaction(function () use ($data, $student, $issuedAt, $dueDate) {
            $student->load([
                'currentEnrollment.schoolYear',
                'currentPaymentAccount',
                'branch',
                'school',
                'classroom',
            ]);

            $enrollment = $student->currentEnrollment;
            $paymentAccount = $student->currentPaymentAccount;

            $insertedInvoices = 0;
            foreach ($data['month'] as $month) {
                $student->invoices()->create([
                    'branch_id' => $student->branch_id,
                    'school_id' => $student->school_id,
                    'student_id' => $student->getKey(),
                    'classroom_id' => $student->classroom_id,
                    'school_year_id' => $enrollment->school_year_id,

                    'branch_name' => $student->branch->name,
                    'school_name' => $student->school->name,
                    'classroom_name' => $student->classroom->name,
                    'school_year_name' => $enrollment->schoolYear->name,
                    'student_name' => $student->name,

                    'type' => InvoiceTypeEnum::MONTHLY_FEE,
                    'month' => $month,

                    'amount' => $paymentAccount->monthly_fee_amount,
                    'total_amount' => $paymentAccount->monthly_fee_amount,

                    'due_date' => $dueDate,
                    'issued_at' => $issuedAt,
                ]);

                $insertedInvoices++;
            }

            return $insertedInvoices;
        });
    }
}
