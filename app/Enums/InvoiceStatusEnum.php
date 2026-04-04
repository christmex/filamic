<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Traits\Equatable;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum InvoiceStatusEnum: int implements HasColor, HasLabel
{
    use Equatable;

    case UNPAID = 1;
    case PAID = 2;
    case VOID = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Bayar',
            self::PAID => 'Lunas',
            self::VOID => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::UNPAID => 'warning',
            self::PAID => 'success',
            self::VOID => 'gray',
        };
    }
}
