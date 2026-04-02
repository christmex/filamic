<?php

declare(strict_types=1);

namespace App\Filament\SupplyHub\Resources\StockMovements\Tables;

use App\Enums\StockMovementTypeEnum;
use App\Models\ProductStockMovement;
use Christmex\FilamentToggleTableGroupAction\Actions\ToggleTableGroupAction;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('item.product.name')
            ->collapsedGroupsByDefault()
            ->paginationMode(PaginationMode::Simple)
            ->searchPlaceholder('Cari Nama Produk/SKU')
            ->groups([
                Group::make('item.product.name')
                    ->collapsible()
                    ->label('Produk'),
                Group::make('item.product.supplier.name')
                    ->collapsible()
                    ->label('Supplier'),
                Group::make('item.product.category.name')
                    ->collapsible()
                    ->label('Kategori'),
            ])
            ->defaultSort('transaction_date', 'desc')
            ->toolbarActions([
                // Action::make('expand_all')
                //     ->label('Toggle Detail Variasi Produk')
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
                TextColumn::make('item.product.name')
                    ->label('Produk')
                    ->description(fn (ProductStockMovement $record) => 'SKU: ' . $record->item->sku)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereRelation('item.product', 'name', 'like', "%{$search}%")
                        ->orWhereRelation('item', 'sku', 'like', "%{$search}%")),
                TextColumn::make('transaction_date')
                    ->label('Tanggal Transaksi')
                    ->date()
                    ->sortable(),
                TextColumn::make('item.variationOptions.formatted_name')
                    ->label('Variasi')
                    ->placeholder('-')
                    ->wrapHeader()
                    ->wrap()
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Jumlah')
                    ->sortable(),
                TextColumn::make('destination_branch')
                    ->label('Cabang Tujuan')
                    ->sortable()
                    ->getStateUsing(fn (ProductStockMovement $record): ?string => $record->type->is(StockMovementTypeEnum::TRANSFER_OUT)
                        ? $record->relatedMovement?->branch?->name
                        : $record->branch->name),
                TextColumn::make('user.name')
                    ->label('Pengguna'),
                TextColumn::make('reference')
                    ->label('Referensi')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Catatan')
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
