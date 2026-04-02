<?php

declare(strict_types=1);

namespace App\Filament\SupplyHub\Resources\ProductItems\Tables;

use App\Enums\GradeEnum;
use App\Enums\LevelEnum;
use App\Filament\SupplyHub\Resources\ProductItems\Pages\ListProductItems;
use App\Filament\SupplyHub\Resources\ProductItems\RelationManagers\ProductItemsRelationManager;
use App\Models\Branch;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\Supplier;
use Christmex\FilamentToggleTableGroupAction\Actions\ToggleTableGroupAction;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $query->with([
                    'stocks',
                    'product.supplier',
                    'product.category',
                    'variationOptions',
                ])->latest();
            })
            ->paginationMode(PaginationMode::Simple)
            ->defaultGroup('product.name')
            ->searchPlaceholder('Cari Nama Produk/SKU')
            ->groups([
                Group::make('product.name')
                    ->collapsible()
                    ->label('Produk'),
                Group::make('product.supplier.name')
                    ->collapsible()
                    ->label('Supplier'),
                Group::make('product.category.name')
                    ->collapsible()
                    ->label('Kategori'),
            ])
            ->collapsedGroupsByDefault()
            ->recordUrl(null)
            ->toolbarActions([
                Action::make('togglePriceMode')
                    ->label(fn ($livewire) => $livewire->priceEditable ? 'Nonaktifkan Mode Edit' : 'Aktifkan Mode Edit')
                    ->icon(fn ($livewire) => $livewire->priceEditable ? 'tabler-lock' : 'tabler-pencil')
                    ->color(fn ($livewire) => $livewire->priceEditable ? 'success' : 'gray')
                    ->action(fn ($livewire) => $livewire->priceEditable = ! $livewire->priceEditable),

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
                ToggleTableGroupAction::make()
                    ->label('Toggle Detail Variasi Produk')
            ])
            ->columns([
                ColumnGroup::make('Informasi Produk', [
                    TextColumn::make('product.name')
                        ->label('Nama')
                        ->description(fn (ProductItem $record) => 'SKU: ' . $record->sku)
                        ->searchable(query: fn (Builder $query, string $search): Builder => $query
                            ->whereRelation('product', 'name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%")),
                    TextColumn::make('variationOptions.formatted_name')
                        ->label('Variasi')
                        ->placeholder('-')
                        ->wrapHeader()
                        ->wrap()
                        ->badge(),
                    TextColumn::make('product.supplier.name')
                        ->label('Supplier')
                        ->placeholder('-')
                        ->badge()
                        ->toggleable()
                        ->toggledHiddenByDefault(),
                    TextColumn::make('product.level')
                        ->label('Jenjang')
                        ->placeholder('-')
                        ->badge()
                        ->toggleable()
                        ->toggledHiddenByDefault(),
                    TextColumn::make('product.grade')
                        ->label('Kelas')
                        ->placeholder('Semua Kelas')
                        ->badge()
                        ->toggleable()
                        ->toggledHiddenByDefault(),
                ]),
                ColumnGroup::make('Harga', [
                    TextInputColumn::make('purchase_price')
                        ->label('Beli')
                        ->rules(['required', 'numeric', 'min:0'])
                        ->disabled(fn ($livewire) => ! $livewire->priceEditable)
                        ->sortable()
                        ->currencyMask(),
                    TextInputColumn::make('sale_price')
                        ->label('Jual')
                        ->rules(['required', 'numeric', 'min:0'])
                        ->disabled(fn ($livewire) => ! $livewire->priceEditable)
                        ->sortable()
                        ->currencyMask(),
                ]),
            ])
            ->pushColumns(self::pushBranchStockColumns())
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Produk')
                    ->trueLabel('Masih Digunakan')
                    ->falseLabel('Tidak Digunakan')
                    ->placeholder('Semua Status')
                    ->default(true),
                SelectFilter::make('level')
                    ->label('Jenjang')
                    ->options(LevelEnum::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                            ? $query->whereRelation('product', 'level', $data['value'])
                            : $query
                    ),
                SelectFilter::make('grade')
                    ->label('Kelas')
                    ->options(GradeEnum::class)
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                            ? $query->whereRelation('product', 'grade', $data['value'])
                            : $query
                    ),
                SelectFilter::make('supplier')
                    ->label('Supplier')
                    ->options(fn () => Supplier::pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                            ? $query->whereRelation('product', 'supplier_id', $data['value'])
                            : $query
                    )
                    ->visibleOn(ListProductItems::class),
                SelectFilter::make('category')
                    ->label('Kategori')
                    ->options(fn () => ProductCategory::pluck('name', 'id'))
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                            ? $query->whereRelation('product', 'product_category_id', $data['value'])
                            : $query
                    )
                    ->visibleOn(ProductItemsRelationManager::class),
            ], FiltersLayout::AboveContent);
    }

    private static function pushBranchStockColumns(): array
    {
        $branches = cache()->remember('filament:product_items_table:branches', 60, fn () => auth()->user()->branches()->get(['id', 'name']));

        $columns = $stocks = [];

        /** @var Branch $branch */
        foreach ($branches as $branch) {

            $stocks[] = TextInputColumn::make('stocks.' . $branch->getKey())
                ->label($branch->name)
                ->type('number')
                ->rules(['required', 'integer', 'min:0'])
                ->disabled(fn ($livewire) => ! $livewire->priceEditable)
                ->getStateUsing(fn ($record) => $record->stocks
                    ->firstWhere('branch_id', $branch->getKey())
                    ->quantity ?? 0)
                ->updateStateUsing(function ($record, $state) use ($branch) {
                    $branch->updateStock($record, (int) $state);
                });
        }

        $columns[] = ColumnGroup::make('Stok', $stocks);

        $columns[] = ToggleColumn::make('is_active')
            ->label('Status')
            ->tooltip('Status Produk Masih Digunakan Atau Tidak')
            ->onColor('success')
            ->offColor('danger')
            ->onIcon('tabler-check')
            ->offIcon('tabler-x');

        return $columns;
    }
}
