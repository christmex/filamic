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

class PayMonthlyFeeInvoice
{
    use AsAction;

    public function handle(Student $student, array $data): bool
    {
        $validated = Validator::make($data, [
            'invoice_ids' => ['required', 'array'],
            'invoice_ids.*' => ['required', 'exists:invoices,id,student_id,' . $student->getKey()],
            'paid_at' => ['required', 'date', 'before_or_equal:now'],
            'payment_method' => ['required', new Enum(PaymentMethodEnum::class)],

            'discount' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
        ])->validate();

        return DB::transaction(function () use ($student, $validated) {
            $invoicesToPay = $student->invoices()
                ->whereIn('id', $validated['invoice_ids'])
                ->unpaidMonthlyFee()
                ->orderBy('due_date', 'asc')
                ->lockForUpdate()
                ->get();

            if ($invoicesToPay->isEmpty()) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Tidak ada tagihan yang dapat diproses. Silakan refresh halaman.',
                ]);
            }

            // Calculate fine AFTER acquiring lock to prevent race conditions
            $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

            if ($invoicesToPay->count() !== count($validated['invoice_ids'])) {
                throw ValidationException::withMessages([
                    'invoice_ids' => 'Beberapa tagihan sudah diproses atau tidak ditemukan. Silakan refresh halaman.',
                ]);
            }

            $maxDiscount = $invoicesToPay->first()->amount + $fine;

            if ($validated['discount'] > $maxDiscount) {
                throw ValidationException::withMessages([
                    'discount' => 'Discount tidak boleh melebihi total tagihan.',
                ]);
            }

            $paymentReference = Invoice::generatePaymentReference();

            foreach ($invoicesToPay as $index => $invoice) {
                $isOldest = ($index === 0);

                $invoice->updateOrFail([
                    'status' => InvoiceStatusEnum::PAID,
                    'paid_at' => $validated['paid_at'],
                    'paid_at_app' => now(),
                    'payment_method' => $validated['payment_method'],
                    'payment_reference' => $paymentReference,
                    'description' => $validated['description'],

                    // Apply fine and discount only to the oldest invoice
                    'fine' => $isOldest ? $fine : 0,
                    'discount' => $isOldest ? $validated['discount'] : 0,

                    // Recalculate total with fine and discount applied
                    'total_amount' => $isOldest
                        ? ($invoice->amount + $fine - $validated['discount'])
                        : $invoice->amount,
                ]);
            }

            return true;
        });
    }
}
