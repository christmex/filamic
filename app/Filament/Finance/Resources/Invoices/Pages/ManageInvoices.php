<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Invoices\Pages;

use App\Actions\PrintDailyInvoiceReport;
use App\Enums\InvoiceTypeEnum;
use App\Filament\Finance\Resources\Invoices\InvoiceResource;
use Filament\Actions\Action;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Malzariey\FilamentDaterangepickerFilter\Fields\DateRangePicker;
use Throwable;

class ManageInvoices extends ManageRecords
{
    protected static string $resource = InvoiceResource::class;

    public function getHeaderActions(): array
    {
        return [
            Action::make('print_invoice_report')
                ->label('Print Laporan Tagihan Yang Sudah Lunas')
                ->icon('tabler-printer')
                ->color('primary')
                ->schema([
                    DateRangePicker::make('paid_at_app')
                        ->required()
                        ->defaultToday()
                        ->label('Tanggal Pembayaran di Aplikasi'),
                    ToggleButtons::make('invoice_type')
                        ->label('Jenis Tagihan')
                        ->options(InvoiceTypeEnum::class)
                        ->required()
                        ->inline(),
                ])
                ->action(function (array $data) {
                    try {
                        $filename = PrintDailyInvoiceReport::run(
                            filament()->getTenant(),
                            $data
                        );

                        if (! $filename) {
                            Notification::make()
                                ->title('Data tidak ditemukan untuk periode ini')
                                ->warning()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Berhasil membuat laporan')
                            ->icon(Heroicon::DocumentText)
                            ->color('success')
                            ->actions([
                                Action::make('view')
                                    ->label('Klik disini untuk lihat')
                                    ->url(asset('storage/' . $filename))
                                    ->link()
                                    ->openUrlInNewTab(),
                            ])
                            ->send();
                    } catch (Throwable $error) {
                        report($error);

                        Notification::make()
                            ->title('Gagal membuat laporan!')
                            ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }
                }),
        ];
    }

    public function getTabs(): array
    {
        $types = InvoiceTypeEnum::cases();
        $tabs = [];

        foreach ($types as $type) {
            $tabs[$type->value] = Tab::make()
                ->label($type->getLabel())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('type', $type->value))
                ->icon($type->getIcon());
        }

        return $tabs;
    }
}
