<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum StudentEnrollmentStatusEnum: int implements HasColor, HasIcon, HasLabel
{
    case DRAFT = 1;
    case ENROLLED = 2;
    case PROMOTED = 3;
    case STAYED = 4;
    case INACTIVE = 5;
    case GRADUATED = 6;
    // case MOVED_INTERNAL = 5;
    // case MOVED_EXTERNAL = 6;
    // case DROPPED_OUT = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::ENROLLED => 'Terdaftar',
            self::PROMOTED => 'Naik Kelas',
            self::STAYED => 'Tinggal Kelas',
            self::INACTIVE => 'Tidak Aktif',
            self::GRADUATED => 'Lulus',
            // self::MOVED_INTERNAL => 'Mutasi Internal',
            // self::MOVED_EXTERNAL => 'Mutasi Keluar',
            // self::DROPPED_OUT => 'Putus Sekolah',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'gray',
            self::ENROLLED => 'success',
            self::PROMOTED => 'success',
            self::STAYED => 'warning',
            self::INACTIVE => 'danger',
            self::GRADUATED => 'info',
            // self::MOVED_INTERNAL, self::MOVED_EXTERNAL => 'warning',
            // self::DROPPED_OUT => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::DRAFT => 'tabler-circle-dashed',
            self::ENROLLED => 'tabler-circle-dashed-check',
            self::PROMOTED => 'tabler-circle-check',
            self::STAYED => 'tabler-circle-dashed',
            self::INACTIVE => 'tabler-circle-x',
            self::GRADUATED => 'tabler-circle-check',
            // self::MOVED_INTERNAL => 'heroicon-m-arrows-right-left',
            // self::MOVED_EXTERNAL => 'heroicon-m-arrow-right-on-rectangle',
            // self::DROPPED_OUT => 'heroicon-m-x-circle',
        };
    }

    /**
     * @return self[]
     */
    public static function getActiveStatuses(): array
    {
        return [
            self::ENROLLED,
        ];
    }

    /**
     * @return self[]
     */
    public static function getInactiveStatuses(): array
    {
        return collect(self::cases())
            ->reject(fn (self $case) => in_array($case, self::getActiveStatuses(), true))
            ->all();
    }
}
