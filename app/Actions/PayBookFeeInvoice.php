<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatusEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class PayBookFeeInvoice
{
    use AsAction;

    public function handle(Student $student, array $data): bool
    {
        $validated = Validator::make($data, [
            'invoice_ids' => ['required', 'array'],
            'invoice_ids.*' => ['required', 'exists:invoices,id,student_id,' . $student->getKey()],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],
            'description' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($student, $validated) {
            $invoicesToPay = $student->invoices()
                ->whereIn('id', $validated['invoice_ids'])
                ->unpaidBookFee()
                ->orderBy('due_date', 'asc')
                ->lockForUpdate()
                ->get();

            if ($invoicesToPay->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Tidak ada tagihan yang dapat diproses. Silakan refresh halaman.',
                ]);
            }

            if ($invoicesToPay->count() !== count($validated['invoice_ids'])) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Beberapa tagihan sudah diproses atau tidak ditemukan. Silakan refresh halaman.',
                ]);
            }

            $paymentGroupReference = Invoice::generateGroupReference();

            foreach ($invoicesToPay as $invoice) {
                $invoice->updateOrFail([
                    'status' => InvoiceStatusEnum::PAID,
                    'paid_at' => $validated['paid_at'],
                    'paid_at_app' => now(),
                    'payment_method' => $validated['payment_method'],
                    'payment_group_reference' => $paymentGroupReference,
                    'description' => $validated['description'],
                ]);
            }

            return true;
        });
    }
}
