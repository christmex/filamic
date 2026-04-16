<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class FinalizeSchoolYearActivation
{
    use AsAction;

    /**
     * @return array{deactivated: int}
     */
    public function handle(string $schoolYearId): array
    {
        return DB::transaction(function () use ($schoolYearId): array {
            $nextSchoolYear = SchoolYear::findOrFail($schoolYearId);

            // Find all currently-active enrollments whose students did NOT
            // receive a new ENROLLED enrollment in the next school year.
            $enrollmentIdsToDeactivate = StudentEnrollment::query()
                ->active()
                ->whereHas('student', function (Builder $query) use ($schoolYearId): void {
                    $query->whereDoesntHave('enrollments', function (Builder $query) use ($schoolYearId): void {
                        $query->where('school_year_id', $schoolYearId)
                            ->where('status', StudentEnrollmentStatusEnum::ENROLLED);
                    });
                })
                ->pluck('id');

            StudentEnrollment::whereIn('id', $enrollmentIdsToDeactivate)
                ->update(['status' => StudentEnrollmentStatusEnum::INACTIVE]);

            // activateExclusively() deactivates the current year and triggers
            // AcademicPeriod::saved, which re-syncs all students' is_active —
            // compensating for the bulk updates above that bypass model events.
            $nextSchoolYear->activateExclusively();

            return ['deactivated' => $enrollmentIdsToDeactivate->count()];
        });
    }
}
