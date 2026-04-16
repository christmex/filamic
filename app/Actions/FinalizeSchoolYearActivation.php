<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\SchoolYear;
use Illuminate\Database\Query\JoinClause;
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

            $activeYearId = SchoolYear::getActive()?->getKey();

            // Single UPDATE via LEFT JOIN — zero IDs loaded into PHP.
            // Deactivates ENROLLED enrollments in the current active year for students who
            // do NOT have an ENROLLED enrollment in the next school year.
            // LEFT JOIN + IS NULL avoids MySQL error 1093 (can't reference updated table
            // in a correlated subquery's FROM clause).
            $deactivated = DB::table('student_enrollments')
                ->leftJoin('student_enrollments as se_next', function (JoinClause $join) use ($schoolYearId): void {
                    $join->on('se_next.student_id', '=', 'student_enrollments.student_id')
                        ->where('se_next.school_year_id', $schoolYearId)
                        ->where('se_next.status', StudentEnrollmentStatusEnum::ENROLLED->value);
                })
                ->where('student_enrollments.school_year_id', $activeYearId)
                ->whereIn('student_enrollments.status', array_map(
                    fn (StudentEnrollmentStatusEnum $s) => $s->value,
                    StudentEnrollmentStatusEnum::getActiveStatuses(),
                ))
                ->whereNull('se_next.student_id')
                ->update(['student_enrollments.status' => StudentEnrollmentStatusEnum::INACTIVE->value]);

            // activateExclusively() deactivates the current year and triggers
            // AcademicPeriod::saved, which re-syncs all students' is_active —
            // compensating for the bulk updates above that bypass model events.
            $nextSchoolYear->activateExclusively();

            return ['deactivated' => $deactivated];
        });
    }
}
