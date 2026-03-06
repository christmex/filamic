<?php

namespace App\Filament\SupplyHub\Resources\Orders\Pages;

use App\Filament\SupplyHub\Resources\Orders\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;
}
