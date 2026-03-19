<?php

declare(strict_types=1);

namespace App\Filament\SupplyHub\Resources\Orders;

use App\Filament\SupplyHub\Resources\Orders\Pages\CreateOrder;
use App\Filament\SupplyHub\Resources\Orders\Pages\EditOrder;
use App\Filament\SupplyHub\Resources\Orders\Pages\ListOrders;
use App\Filament\SupplyHub\Resources\Orders\Schemas\OrderForm;
use App\Filament\SupplyHub\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $isScopedToTenant = false;

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
