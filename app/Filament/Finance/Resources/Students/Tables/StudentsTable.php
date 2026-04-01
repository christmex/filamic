<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\Tables;

use App\Actions\PayMonthlyFeeInvoice;
use App\Actions\PrintMonthlyFeeInvoice;
use App\Enums\MonthEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\SchoolYear;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\RawJs;
use Filament\Tables\Columns\Layout\View;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Throwable;

/**
 * @method Branch filament()->getTenant()
 */
class StudentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with(['currentPaymentAccount', 'unpaidMonthlyFee', 'paidMonthlyFee', 'unpaidInvoices']);
            })
            ->paginationMode(PaginationMode::Simple)
            ->contentGrid([
                'sm' => 1,
                'md' => 2,
                'lg' => 4,
            ])
            ->paginated([8, 16, 32, 64])
            ->recordUrl(null)
            ->searchPlaceholder('Cari Nama Peserta Didik | Nomor VA')
            ->columns([
                View::make('filament.finance.resources.students.tables.column')
                    ->components([
                        TextColumn::make('name')
                            ->label('Nama')
                            ->searchable()
                            ->sortable(),
                        TextColumn::make('currentPaymentAccount')
                            ->label('Nomor Virtual Account')
                            ->searchable(query: function (Builder $query, string $search): Builder {
                                return $query->whereHas('currentPaymentAccount', function ($query) use ($search) {
                                    $query->where('monthly_fee_virtual_account', 'like', "%{$search}%")
                                        ->orWhere('book_fee_virtual_account', 'like', "%{$search}%");
                                });
                            }),
                    ]),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Kelas')
                    ->options(function () {
                        /** @var Branch $tenant */
                        $tenant = filament()->getTenant();

                        return Classroom::with('school')
                            ->whereIn('school_id', $tenant->schools->pluck('id'))
                            ->get()
                            ->groupBy('school.name')
                            ->map(fn ($classroom) => $classroom->pluck('name', 'id'));
                    })
                    ->preload()
                    ->optionsLimit(20)
                    ->searchable()
                    ->multiple(),
                Filter::make('invoices')
                    ->schema([
                        Select::make('month')
                            ->options(MonthEnum::class)
                            ->label('Bulan Nunggak')
                            ->searchable()
                            ->multiple()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['month'], function (Builder $query) use ($data) {

                            $query->whereHas('invoices', function ($query) use ($data) {
                                /** @var Invoice $query */
                                // @phpstan-ignore-next-line
                                $query->whereIn('month', $data['month'])->unpaidMonthlyFee();
                            });

                        });
                    })
                    ->indicateUsing(function (array $data): ?string {
                        $indicator = null;

                        if (filled($data['month'])) {
                            $indicator .= 'Bulan Nunggak: ' . collect($data['month'])->map(fn ($month) => MonthEnum::from((int) $month)->getLabel())->implode(' & ');
                        }

                        return $indicator;
                    }),

            ], FiltersLayout::Modal)
            ->recordActions([
                DeleteAction::make()->visible(fn (Student $record) => $record->canBeDelete()),
                // EditAction::make(),
                ActionGroup::make([
                    Action::make('payMonthlyFeeAction')
                        ->label('Uang Sekolah')
                        ->visible(fn (Student $record) => $record->unpaidMonthlyFee->isNotEmpty())
                        ->modalHeading('Bayar Tagihan Uang Sekolah')
                        ->schema([
                            TextInput::make('total_invoice')
                                ->label('Total Tagihan')
                                ->disabled()
                                ->mask(RawJs::make('$money($input)'))
                                ->stripCharacters(',')
                                ->prefix('Rp'),
                            DateTimePicker::make('paid_at')
                                ->required()
                                ->maxDate(fn () => now())
                                ->label('Tanggal Bayar')
                                ->hint('Tanggal Sesuai Rekening Koran/Tanggal Saat Bayar')
                                ->default(fn () => now()),
                            CheckboxList::make('invoice_ids')
                                ->label('Tagihan Berdasarkan Bulan')
                                ->required()
                                ->bulkToggleable()
                                ->options(function (Student $record) {
                                    // TODO: extract this to method and cache since it also used in the description
                                    /** @var Builder|Invoice $query */
                                    // @phpstan-ignore-next-line
                                    $query = $record->invoices();

                                    return $query
                                        ->unpaidMonthlyFee()
                                        ->orderBy('due_date')
                                        ->get()
                                        ->mapWithKeys(fn (Invoice $invoice) => [
                                            $invoice->getKey() => sprintf('%s (%s)', $invoice->month->getLabel(), $invoice->school_year_name),
                                        ]);
                                })
                                ->columns(3)
                                ->descriptions(function (Student $record) {
                                    /** @var Builder|Invoice $query */
                                    // @phpstan-ignore-next-line
                                    $query = $record->invoices();

                                    return $query
                                        ->unpaidMonthlyFee()
                                        ->orderBy('due_date')
                                        ->get()
                                        ->pluck('formatted_amount', 'id');
                                })
                                ->gridDirection('row')
                                ->live()
                                ->afterStateUpdated(function (Set $set, ?array $state, Student $record) {
                                    $totalAmount = $record->invoices()
                                        ->whereIn('id', $state ?? [])
                                        ->sum('amount');

                                    $totalAmount = Number::format((int) $totalAmount, locale: config('app.locale'));

                                    $set('total_invoice', $totalAmount);
                                }),
                            Group::make([
                                TextInput::make('fine')
                                    ->label('Denda')
                                    ->default(fn (Student $record) => Invoice::calculateFineFromOldestUnpaidInvoice($record))
                                    ->numeric()
                                    ->readOnly()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->stripCharacters(',')
                                    ->prefix('Rp')
                                    ->minValue(0),
                                TextInput::make('discount')
                                    ->label('Diskon')
                                    ->minValue(0)
                                    ->numeric()
                                    ->mask(RawJs::make('$money($input)'))
                                    ->stripCharacters(',')
                                    ->prefix('Rp')
                                    ->default(0),
                            ])->columns(2),
                            Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->required()
                                ->options(PaymentMethodEnum::class),
                            Textarea::make('description')
                                ->label('Keterangan')
                                ->hint('Deskripsi Ini Akan Di Buat Untuk Semua Tagihan Yang Terpilih.'),
                        ])
                        ->action(function (Student $record, array $data) {
                            try {
                                $payMonthlyFeeInvoice = PayMonthlyFeeInvoice::run($record, $data);

                                if ($payMonthlyFeeInvoice === false) {
                                    Notification::make()
                                        ->title('Tagihan tidak dibayarkan!')
                                        ->info()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title('Berhasil membayar tagihan!')
                                    ->success()
                                    ->send();

                            } catch (Throwable $error) {
                                report($error);

                                Notification::make()
                                    ->title('Gagal membayar tagihan!')
                                    ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }
                        }),
                ])
                    ->icon('tabler-invoice')
                    ->label('Bayar')
                    ->link()
                    ->color('success'),
                ActionGroup::make([
                    Action::make('printInvoice')
                        ->visible(fn (Student $record) => $record->paidMonthlyFee->isNotEmpty())
                        ->requiresConfirmation()
                        ->label('Uang Sekolah')
                        ->schema([
                            Select::make('school_year_id')
                                ->label('Tahun Ajaran')
                                ->live()
                                ->options(fn () => SchoolYear::get()->pluck('name', 'id'))
                                ->default(fn () => SchoolYear::getActive()?->getKey()),
                            CheckboxList::make('invoice_ids')
                                ->label('Tagihan')
                                ->live()
                                ->bulkToggleable()
                                ->required()
                                ->options(fn (Student $record, Get $get): array => $record
                                    ->paidMonthlyFee()
                                    ->where('school_year_id', $get('school_year_id'))
                                    ->orderBy('month')
                                    ->get()
                                    ->mapWithKeys(function ($invoice): array {
                                        /** @var Invoice $invoice */
                                        return [
                                            (string) $invoice->getKey() => $invoice->month->getLabel(),
                                        ];
                                    })
                                    ->all()
                                )
                                ->columns(3)
                                ->gridDirection('row'),
                        ])
                        ->action(function (Student $record, array $data) {
                            try {
                                $printMonthlyFeeInvoice = PrintMonthlyFeeInvoice::run(
                                    $record,
                                    $data
                                );

                                if (blank($printMonthlyFeeInvoice)) {
                                    Notification::make()
                                        ->title('Gagal membuat pdf tagihan!')
                                        ->info()
                                        ->send();

                                    return;
                                }

                                Notification::make()
                                    ->title('Berhasil membuat pdf tagihan!')
                                    ->success()
                                    ->actions([
                                        Action::make('view')
                                            ->label('Klik disini untuk lihat')
                                            ->url(Storage::url($printMonthlyFeeInvoice))
                                            ->link()
                                            ->openUrlInNewTab(),
                                    ])
                                    ->send();

                            } catch (Throwable $error) {
                                report($error);

                                Notification::make()
                                    ->title('Gagal membuat pdf tagihan!')
                                    ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                    ->danger()
                                    ->persistent()
                                    ->send();

                                return;
                            }
                        }),
                ])
                    ->icon('tabler-printer')
                    ->label('Cetak')
                    ->link()
                    ->color('gray')
                    ->dropdownPlacement('bottom-right'),

            ]);
    }
}
