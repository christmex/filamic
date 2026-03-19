<?php

declare(strict_types=1);

namespace App\Filament\SupplyHub\Resources\Orders\Pages;

use App\Filament\SupplyHub\Resources\Orders\OrderResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
