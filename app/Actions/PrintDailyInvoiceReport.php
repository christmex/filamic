<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceTypeEnum;
use App\Models\Branch;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

class PrintDailyInvoiceReport
{
    use AsAction;

    public function handle(Branch $branch, array $data): ?string
    {
        $dates = explode(' - ', $data['paid_at_app']);
        $startDate = Carbon::createFromFormat('d/m/Y', $dates[0])->startOfDay();
        $endDate = Carbon::createFromFormat('d/m/Y', $dates[1])->endOfDay();

        $invoices = Invoice::query()
            ->where('branch_id', $branch->getKey())
            ->whereBetween('paid_at_app', [$startDate, $endDate])
            ->where('type', $data['invoice_type'])
            ->get();

        if ($invoices->isEmpty()) {
            return null;
        }

        $totalAmount = $invoices->sum('amount');
        $totalFine = $invoices->sum('fine');

        $filename = 'pdf/daily-invoice-report-' . str($branch->name)->slug() . '.pdf';

        $pdf = Pdf::loadView('filament.finance.pdf.daily-invoice-report', [
            'invoices' => $invoices,
            'branch' => $branch,
            'totalAmount' => $totalAmount,
            'totalFine' => $totalFine,
            'isMonthlyInvoice' => $data['invoice_type'] === InvoiceTypeEnum::MONTHLY_FEE,
        ])->setPaper([0, 0, 609.449, 935.433], 'portrait');

        // Simpan ke disk 'public' agar bisa diakses via URL asset()
        Storage::disk('public')->put($filename, $pdf->output());

        return $filename;
    }
}
