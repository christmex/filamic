<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GradeEnum: int implements HasLabel
{
    // TPA
    case TODDLER = 1;
    case NURSERY = 2;
    case KINDERGARTEN_A = 3;
    case KINDERGARTEN_B = 4;

    // SD
    case GRADE_1 = 5;
    case GRADE_2 = 6;
    case GRADE_3 = 7;
    case GRADE_4 = 8;
    case GRADE_5 = 9;
    case GRADE_6 = 10;

    // SMP
    case GRADE_7 = 11;
    case GRADE_8 = 12;
    case GRADE_9 = 13;

    // SMA
    case GRADE_10 = 14;
    case GRADE_11 = 15;
    case GRADE_12 = 16;

    public function getLabel(): string
    {
        return match ($this) {
            self::TODDLER => 'Taman Penitipan Anak - (Toddler)',
            self::NURSERY => 'Kelompok Bermain - (Nursery)',
            self::KINDERGARTEN_A => 'TK A - (Kindergarten A)',
            self::KINDERGARTEN_B => 'TK B - (Kindergarten B)',
            self::GRADE_1 => 'SD Kelas 1 - (Grade 1)',
            self::GRADE_2 => 'SD Kelas 2 - (Grade 2)',
            self::GRADE_3 => 'SD Kelas 3 - (Grade 3)',
            self::GRADE_4 => 'SD Kelas 4 - (Grade 4)',
            self::GRADE_5 => 'SD Kelas 5 - (Grade 5)',
            self::GRADE_6 => 'SD Kelas 6 - (Grade 6)',
            self::GRADE_7 => 'SMP Kelas 7 - (Grade 7)',
            self::GRADE_8 => 'SMP Kelas 8 - (Grade 8)',
            self::GRADE_9 => 'SMP Kelas 9 - (Grade 9)',
            self::GRADE_10 => 'SMA Kelas 10 - (Grade 10)',
            self::GRADE_11 => 'SMA Kelas 11 - (Grade 11)',
            self::GRADE_12 => 'SMA Kelas 12 - (Grade 12)',
        };
    }

    public static function forLevel(int | LevelEnum | null $level): array
    {
        $levelValue = $level instanceof LevelEnum ? $level->value : $level;

        return match ($levelValue) {
            1 => [self::TODDLER, self::NURSERY, self::KINDERGARTEN_A, self::KINDERGARTEN_B],
            2 => [self::GRADE_1, self::GRADE_2, self::GRADE_3, self::GRADE_4, self::GRADE_5, self::GRADE_6],
            3 => [self::GRADE_7, self::GRADE_8, self::GRADE_9],
            4 => [self::GRADE_10, self::GRADE_11, self::GRADE_12],
            default => [],
        };
    }

    public static function finalYears(): array
    {
        return [self::KINDERGARTEN_B, self::GRADE_6, self::GRADE_9, self::GRADE_12];
    }

    public function isFinalYear(): bool
    {
        return in_array($this, self::finalYears(), true);
    }
}
