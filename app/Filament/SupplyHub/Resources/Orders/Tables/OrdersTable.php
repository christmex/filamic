<?php

declare(strict_types=1);

namespace App\Filament\SupplyHub\Resources\Orders\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('supplier.name')
                    ->searchable(),
                TextColumn::make('schoolYear.name')
                    ->searchable(),
                TextColumn::make('ordered_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('discount_percentage')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_items')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coordinator')
                    ->searchable(),
                TextColumn::make('person_in_charge')
                    ->searchable(),
            ])
            ->filters([

            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
