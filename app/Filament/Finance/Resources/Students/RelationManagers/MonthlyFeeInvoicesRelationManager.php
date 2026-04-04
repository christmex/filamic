<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\RelationManagers;

use App\Actions\GenerateBulkMonthlyFeeInvoice;
use App\Enums\InvoiceStatusEnum;
use App\Enums\MonthEnum;
use App\Filament\Finance\Actions\RepeatPaymentAction;
use App\Models\Invoice;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Group;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class MonthlyFeeInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                /** @var Builder|Invoice $query */
                $query->monthlyFee();
            })
            ->heading('Uang Sekolah')
            ->paginationMode(PaginationMode::Simple)
            ->recordTitleAttribute('id')
            ->headerActions([
                Action::make('createInvoice')
                    ->label('Buat Tagihan SPP')
                    ->icon('tabler-invoice')
                    ->color('success')
                    ->modalIconColor('success')
                    ->modalIcon('tabler-invoice')
                    ->modalDescription(function () {
                        $schoolYear = SchoolYear::getActive();
                        $schoolTerm = SchoolTerm::getActive();

                        if (blank($schoolYear) || blank($schoolTerm)) {
                            return 'Tahun Ajaran/Semester belum aktif.';
                        }

                        return str("Tahun Ajaran: **{$schoolYear->name}**  \n" .
                            "Semester: **{$schoolTerm->name->getLabel()}**")
                            ->markdown()
                            ->toHtmlString();
                    })
                    ->schema([
                        Group::make([
                            Select::make('month')
                                ->options(function () {
                                    $currentTerm = SchoolTerm::getActive();
                                    if (blank($currentTerm)) {
                                        return [];
                                    }

                                    /** @var Student $student */
                                    $student = $this->getOwnerRecord();
                                    $invoices = $student->invoices()
                                        ->monthlyFeeForThisSchoolYear()
                                        ->pluck('month')
                                        ->map(fn ($month) => $month->value)
                                        ->toArray();

                                    $allowedMonths = array_diff($currentTerm->getAllowedMonths(), $invoices);

                                    return collect(MonthEnum::filterByMonths($allowedMonths))
                                        ->mapWithKeys(fn ($month) => [$month->value => $month->getLabel()])
                                        ->toArray();
                                })
                                ->required()
                                ->label('Bulan')
                                ->live()
                                ->selectablePlaceholder(false)
                                ->multiple()
                                ->columnSpanFull(),
                            DatePicker::make('issued_at')
                                ->label('Tagihan Dibuka')
                                ->required()
                                ->default(now()->setDay(28)),
                            DatePicker::make('due_date')
                                ->label('Tagihan Berakhir')
                                ->required()
                                ->after('issued_at')
                                ->default(now()->addMonth()->setDay(20)),
                        ])
                            ->columns(2),
                    ])
                    ->action(function (array $data) {
                        try {
                            $generateBulkMonthlyFeeInvoice = GenerateBulkMonthlyFeeInvoice::run($data, $this->getOwnerRecord());

                            if ($generateBulkMonthlyFeeInvoice === 0) {
                                Notification::make()
                                    ->title('Tagihan tidak dibuat!')
                                    ->body('Tidak ada siswa yang memenuhi syarat pembuatan tagihan.')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title('Berhasil membuat tagihan!')
                                ->body("{$generateBulkMonthlyFeeInvoice} tagihan baru dibuat.")
                                ->success()
                                ->send();

                        } catch (\Illuminate\Database\QueryException $error) {
                            if ($error->getCode() === '23000' && str_contains(mb_strtolower($error->getMessage()), 'fingerprint')) {
                                Notification::make()
                                    ->title('Invoice sudah dibuat sebelumnya!')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            throw $error;
                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal membuat tagihan!')
                                ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }
                    }),
            ])
            ->columns([
                TextColumn::make('reference_number'),
                TextColumn::make('schoolYear.name'),
                TextColumn::make('month')
                    ->label('Bulan')
                    ->formatStateUsing(fn (MonthEnum $state) => $state->getLabel()),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('total_amount')
                    ->numeric(),
                TextColumn::make('issued_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('payment_method'),
            ])
            ->recordActions([
                RepeatPaymentAction::make(),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Tahun Ajaran')
                    ->options(fn () => SchoolYear::get()->pluck('name', 'id'))
                    ->default(SchoolYear::getActive()?->getKey()),
                SelectFilter::make('status')
                    ->options(InvoiceStatusEnum::class),
                // ->default(InvoiceStatusEnum::UNPAID->value),
            ]);
    }
}
