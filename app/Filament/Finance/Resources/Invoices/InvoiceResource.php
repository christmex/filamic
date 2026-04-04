<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Invoices;

use App\Enums\InvoiceStatusEnum;
use App\Enums\MonthEnum;
use App\Enums\PaymentMethodEnum;
use App\Filament\Finance\Resources\Invoices\Pages\ManageInvoices;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\SchoolYear;
use App\Models\Student;
use BackedEnum;
use Christmex\FilamentToggleTableGroupAction\Actions\ToggleTableGroupAction;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string | BackedEnum | null $navigationIcon = 'tabler-invoice';

    protected static ?string $recordTitleAttribute = 'reference_number';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string
    {
        return 'Tagihan';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reference_number')
            ->defaultGroup('student.name')
            ->paginationMode(PaginationMode::Simple)
            ->groups([
                Group::make('student.name')
                    ->collapsible()
                    ->label('Peserta Didik'),
            ])
            ->collapsedGroupsByDefault()
            ->recordUrl(null)
            ->toolbarActions([
                // Action::make('expand_all')
                //     ->label('Toggle Detail Tagihan')
                //     ->icon(Heroicon::ArrowsPointingOut)
                //     ->color('gray')
                //     ->alpineClickHandler("
                //         const collapsed = document.querySelectorAll('.fi-ta-group-header.fi-collapsed');
                //         if (collapsed.length > 0) {
                //             collapsed.forEach(el => el.click());
                //         } else {
                //             document.querySelectorAll('.fi-ta-group-header:not(.fi-collapsed)').forEach(el => el.click());
                //         }
                //     "),
                ToggleTableGroupAction::make(),
            ])
            ->columns([
                TextColumn::make('student_name')
                    ->label('Nama Peserta Didik')
                    ->description(fn (Invoice $record) => 'Kelas: ' . $record->classroom_name)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('student_name', 'like', "%{$search}%")
                        ->orWhere('classroom_name', 'like', "%{$search}%")),
                TextColumn::make('month')
                    ->badge()
                    ->label('Bulan')
                    ->hidden(fn (ManageInvoices $livewire) => $livewire->activeTab == 2)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->currency('IDR')
                    ->hidden(fn (ManageInvoices $livewire) => $livewire->activeTab == 2)
                    ->sortable(),
                TextColumn::make('fine')
                    ->label('Denda')
                    ->currency('IDR')
                    ->hidden(fn (ManageInvoices $livewire) => $livewire->activeTab == 2)
                    ->sortable(),
                TextColumn::make('discount')
                    ->label('Diskon')
                    ->currency('IDR')
                    ->hidden(fn (ManageInvoices $livewire) => $livewire->activeTab == 2)
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label('Total')
                    ->currency('IDR')
                    ->sortable(),
                TextColumn::make('issued_at')
                    ->label('VA Buka')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('VA Tutup')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Jenis Pembayaran')
                    ->badge()
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label('Tanggal Bayar di Bank')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('paid_at_app')
                    ->label('Tanggal Bayar di Aplikasi')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('payment_group_reference')
                    ->label('No. Grup Referensi')
                    ->searchable(),
                TextColumn::make('reference_number')
                    ->label('No. Referensi')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('school_year_id')
                    ->label('Tahun Ajaran')
                    ->options(SchoolYear::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->multiple()
                    ->default([SchoolYear::getActive()?->getKey()]),
                SelectFilter::make('month')
                    ->label('Bulan')
                    ->options(MonthEnum::class)
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(InvoiceStatusEnum::class)
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('payment_method')
                    ->options(PaymentMethodEnum::class)
                    ->label('Metode Pembayaran')
                    ->multiple(),
                SelectFilter::make('classroom_name')
                    ->label('Kelas')
                    ->options(function () {

                        /** @var \App\Models\Branch $branch */
                        $branch = filament()->getTenant();

                        $schoolIds = $branch->schools->pluck('id')->toArray();

                        return Classroom::whereIn('school_id', $schoolIds)
                            ->with('school')
                            ->get()
                            ->groupBy('school.name')
                            ->map(fn ($classroom) => $classroom->pluck('name', 'id'));
                    })
                    ->searchable()
                    ->preload()
                    ->optionsLimit(10)
                    ->multiple(),
                SelectFilter::make('student_name')
                    ->label('Siswa Tertentu')
                    ->options(Student::pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->optionsLimit(10)
                    ->multiple(),
                DateRangeFilter::make('paid_at_app')
                    ->label('Tanggal Pembayaran di Aplikasi'),
                DateRangeFilter::make('paid_at')
                    ->label('Tanggal Pembayaran di Bank'),
            ], FiltersLayout::AboveContent);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageInvoices::route('/'),
        ];
    }
}
