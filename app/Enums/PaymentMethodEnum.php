<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethodEnum: int implements HasLabel
{
    case VA = 1;
    case EDC_MANDIRI = 2;
    case EDC_BCA = 3;
    // case CASH = 4;

    public function getLabel(): string
    {
        return match ($this) {
            self::VA => 'Virtual Account',
            self::EDC_MANDIRI => 'EDC Mandiri',
            self::EDC_BCA => 'EDC BCA',
            // self::CASH => 'Tunai / Cash',
        };
    }
}
