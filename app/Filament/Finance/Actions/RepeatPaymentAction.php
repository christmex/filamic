<?php

declare(strict_types=1);

namespace App\Filament\Finance\Actions;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

class RepeatPaymentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'repeatPayment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->requiresConfirmation();
        $this->icon('tabler-repeat');
        $this->tooltip('Ulangi Pembayaran');
        $this->modalIcon('tabler-repeat');
        $this->modalHeading('Ulangi Pembayaran');
        $this->modalDescription(str('Tindakan ini akan mengembalikan status pembayaran dari **LUNAS** menjadi **BELUM BAYAR**')->markdown()->toHtmlString());
        $this->iconButton();
        $this->visible(fn (Invoice $record) => $record->status->is(InvoiceStatusEnum::PAID));
        $this->action(function (Invoice $record) {
            try {
                if (blank($record)) {
                    Notification::make()
                        ->title('Data tidak ditemukan')
                        ->warning()
                        ->send();

                    return;
                }

                $record->repeatPayment();

                Notification::make()
                    ->title('Berhasil mengulangi pembayaran')
                    ->color('success')
                    ->send();

                return true;

            } catch (Throwable $error) {
                report($error);

                Notification::make()
                    ->title('Gagal mengulangi pembayaran!')
                    ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        });
    }
}
