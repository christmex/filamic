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

class GenerateBookFeeInvoice
{
    use AsAction;

    public function handle(Branch $branch, array $data): int
    {
        $validated = Validator::make($data, [
            'issued_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:issued_at'],
            'increase_book_cost' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $issuedAt = $validated['issued_at'];
        $dueDate = $validated['due_date'];
        $increaseBookCost = (int) ($validated['increase_book_cost'] ?? 0);

        /** @var Builder|Student $getStudentsQuery */
        // @phpstan-ignore-next-line
        $getStudentsQuery = $branch->students();

        $students = $getStudentsQuery
            ->active()
            ->notInFinalYears()
            ->whereHas('currentEnrollment')
            ->whereHas('currentPaymentAccount', function ($query) {
                $query->where('book_fee_amount', '>', 0);
            })
            ->whereDoesntHave('invoices', function ($query) {
                /** @var Invoice $query */
                // @phpstan-ignore-next-line
                $query->bookFeeForNextSchoolYear();
            })
            ->with([
                'school',
                'currentPaymentAccount',
                'currentEnrollment.classroom',
                'currentEnrollment.schoolYear',
            ])
            ->get();

        if ($students->isEmpty()) {
            return 0;
        }

        $newInvoices = $students->map(function (Student $student) use ($issuedAt, $dueDate, $increaseBookCost, $branch) {
            $enrollment = $student->currentEnrollment;
            $paymentAccount = $student->currentPaymentAccount;

            $prepareFingerprint = [
                'type' => InvoiceTypeEnum::BOOK_FEE->value,
                'student_id' => $student->getKey(),
                'school_year_id' => $enrollment->school_year_id,
            ];

            $amount = $paymentAccount->book_fee_amount + $increaseBookCost;

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

                'type' => InvoiceTypeEnum::BOOK_FEE,

                'amount' => $amount + $paymentAccount->book_fee_amount,
                'total_amount' => $amount + $paymentAccount->book_fee_amount,

                'due_date' => $dueDate,
                'issued_at' => $issuedAt,
            ];

            return $preparedData;
        })->toArray();

        return DB::transaction(function () use ($newInvoices) {
            foreach (array_chunk($newInvoices, 500) as $chunk) {
                Invoice::upsert($chunk, ['fingerprint'], ['amount', 'total_amount', 'due_date', 'issued_at']);
            }

            return count($newInvoices);
        });
    }
}
