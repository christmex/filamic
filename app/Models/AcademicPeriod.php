<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Traits\HasActiveState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
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

            // Query directly to avoid the now-stale cache after deletion above.
            $activeYearId = static::query()->active()->value('id');

            if ($activeYearId === null) {
                Student::query()->active()->update(['is_active' => false]);

                return;
            }

            $activeStatuses = array_map(
                fn (StudentEnrollmentStatusEnum $status) => $status->value,
                StudentEnrollmentStatusEnum::getActiveStatuses(),
            );

            DB::transaction(function () use ($activeYearId, $activeStatuses): void {
                // Activate students with an ENROLLED enrollment in the new active year
                // and denormalise branch/school/classroom from that enrollment.
                DB::table('students')
                    ->join('student_enrollments as se', function (JoinClause $join) use ($activeYearId, $activeStatuses): void {
                        $join->on('se.student_id', '=', 'students.id')
                            ->where('se.school_year_id', '=', $activeYearId)
                            ->whereIn('se.status', $activeStatuses);
                    })
                    ->update([
                        'students.is_active' => true,
                        'students.branch_id' => DB::raw('se.branch_id'),
                        'students.school_id' => DB::raw('se.school_id'),
                        'students.classroom_id' => DB::raw('se.classroom_id'),
                    ]);

                // Deactivate students who no longer have an active enrollment.
                DB::table('students')
                    ->whereNotExists(function (QueryBuilder $query) use ($activeYearId, $activeStatuses): void {
                        $query->select(DB::raw(1))
                            ->from('student_enrollments')
                            ->whereColumn('student_enrollments.student_id', 'students.id')
                            ->where('school_year_id', '=', $activeYearId)
                            ->whereIn('status', $activeStatuses);
                    })
                    ->where('is_active', true)
                    ->update(['is_active' => false]);
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
