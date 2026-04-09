<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceTypeEnum;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\BookFeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class GenerateBookFeeInvoice
{
    use AsAction;

    public function __construct(
        // @phpstan-ignore property.onlyWritten (used below the guard; restore when student_payment_accounts is implemented)
        private readonly BookFeeService $bookFeeService
    ) {}

    public function handle(Branch $branch, array $data): ?array
    {
        // TODO: Remove this guard and implement student_payment_accounts lookup when that table is built.
        throw new RuntimeException('Invoice generation is disabled: student payment accounts are not yet implemented.');
        // @phpstan-ignore-next-line deadCode.unreachable (intentionally preserved — implementation to be restored when student_payment_accounts is built)
        $validated = Validator::make($data, [
            'issued_at' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:issued_at'],
            'increase_book_cost' => ['nullable', 'numeric', 'min:0'],
        ])->validate();

        $issuedAt = $validated['issued_at'];
        $dueDate = $validated['due_date'];
        $increaseBookCost = (int) ($validated['increase_book_cost'] ?? 0);

        $unpaidInvoices = $this->bookFeeService->getUnpaidInvoices($branch);

        $studentsWithoutInvoice = $this->bookFeeService->getStudentsWithoutInvoice($branch);

        $newInvoices = $studentsWithoutInvoice->map(function (Student $student) use ($issuedAt, $dueDate, $increaseBookCost, $branch) {
            $enrollment = $student->currentEnrollment;

            $prepareFingerprint = [
                'type' => InvoiceTypeEnum::BOOK_FEE->value,
                'student_id' => $student->getKey(),
                'school_year_id' => $enrollment->school_year_id,
            ];

            $amount = $student->book_fee_amount + $increaseBookCost;

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

                'virtual_account_number' => $student->book_fee_virtual_account,
                'amount' => $amount,
                'total_amount' => $amount,

                'due_date' => $dueDate,
                'issued_at' => $issuedAt,
            ];

            return $preparedData;
        })->toArray();

        if ($unpaidInvoices->isEmpty() && empty($newInvoices)) {
            return null;
        }

        return DB::transaction(function () use ($unpaidInvoices, $issuedAt, $dueDate, $increaseBookCost, $newInvoices) {

            // NOTE: I know this is causes N+1 queries, but after a lot consideration we choose this way, instead using package or raq sql, since it only looping hundred of data
            // for update invoice
            $unpaidInvoices->each(function (Invoice $invoice) use ($increaseBookCost, $dueDate, $issuedAt) {

                $newAmount = $invoice->student->book_fee_amount + $increaseBookCost;

                $invoice->student->update([
                    'book_fee_amount' => $newAmount,
                ]);

                $invoice->update([
                    'amount' => $newAmount,
                    'total_amount' => $newAmount,
                    'due_date' => $dueDate,
                    'issued_at' => $issuedAt,
                ]);
            });

            // NOTE: I know this is causes N+1 queries, but after a lot consideration we choose this way, instead using package or raq sql, since it only looping hundred of data
            // for new invoice
            foreach ($newInvoices as $invoice) {
                Invoice::create($invoice);
                Student::where('id', $invoice['student_id'])->update([
                    'book_fee_amount' => $invoice['total_amount'],
                ]);
            }

            return [
                'updated' => $unpaidInvoices->count(),
                'created' => count($newInvoices),
            ];
        });

    }
}
