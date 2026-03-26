<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\HasActiveState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property bool $is_active
 */
abstract class AcademicPeriod extends Model
{
    use HasActiveState;

    protected static function booted(): void
    {
        static::saving(function ($model) {
            if ($model->is_active) {
                $model::deactivateOthers();
            }
        });

        static::saved(function ($model) {
            if (! $model->wasChanged('is_active')) {
                return;
            }

            cache()->deleteMultiple(['academic_period_ready', static::getActiveCacheKey()]);

            DB::transaction(function () {
                Student::query()
                    ->active()
                    ->orWhereHas('enrollments', fn ($enrollments) => $enrollments->active()) // @phpstan-ignore-line
                    ->with(['currentEnrollment', 'paymentAccounts'])
                    ->chunkById(200, function ($students) {
                        foreach ($students as $student) {
                            /** @var Student $student */
                            $student->syncActiveStatus();
                        }
                    });
            });
        });
    }

    public static function getActive(): ?static
    {
        return cache()->remember(static::getActiveCacheKey(), now()->addDay(), fn () => static::query()->active()->first());
    }

    public static function getActiveCacheKey(): string
    {
        $name = str(class_basename(static::class))->snake();

        return "active_{$name}_record";
    }
}
