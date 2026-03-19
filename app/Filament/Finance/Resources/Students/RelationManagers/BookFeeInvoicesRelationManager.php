<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\RelationManagers;

use App\Enums\InvoiceStatusEnum;
use App\Models\Invoice;
use App\Models\SchoolYear;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookFeeInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                /** @var Builder|Invoice $query */
                $query->bookFee();
            })
            ->heading('Uang Buku')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('reference_number'),
                TextColumn::make('school_year_name')
                    ->badge(),
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
            ->filters([
                // SelectFilter::make('school_year_id')
                    // ->label('Tahun Ajaran')
                    // ->options(fn () => SchoolYear::get()->pluck('name', 'id')),
                    // ->default(SchoolYear::getActive()?->getKey()),
                // SelectFilter::make('status')
                //     ->options(InvoiceStatusEnum::class)
                    // ->default(InvoiceStatusEnum::UNPAID->value),
            ]);
    }
}
