<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class ActivateNextSchoolYear
{
    use AsAction;

    public function handle(): int
    {
        $nextSchoolYear = SchoolYear::getNextSchoolYearOrFail();

        $drafts = StudentEnrollment::draft()
            ->where('school_year_id', $nextSchoolYear->getKey())
            ->with('student', 'student.classroom', 'student.currentEnrollment', 'classroom')
            ->get();

        if ($drafts->whereNull('classroom_id')->isNotEmpty()) {
            throw new InvalidArgumentException('Terdapat data draft siswa yang kelas nya masih kosong');
        }

        return DB::transaction(function () use ($drafts, $nextSchoolYear) {
            $processed = 0;

            foreach ($drafts as $draft) {
                if (! $draft->student->isActive()) {
                    continue;
                }

                $draft->update([
                    'status' => StudentEnrollmentStatusEnum::ENROLLED,
                ]);

                $oldGrade = $draft->student->classroom->grade;

                if ($oldGrade === $draft->classroom->grade) {
                    $draft->student->currentEnrollment->update([
                        'status' => StudentEnrollmentStatusEnum::STAYED,
                    ]);
                    $processed++;

                    continue;
                }

                if ($oldGrade->isFinalYear()) {
                    $draft->student->currentEnrollment->update([
                        'status' => StudentEnrollmentStatusEnum::GRADUATED,
                    ]);
                    $processed++;

                    continue;
                }

                $draft->student->currentEnrollment->update([
                    'status' => StudentEnrollmentStatusEnum::PROMOTED,
                ]);
                $processed++;
            }

            // Set current enrollment to INACTIVE for active students
            // who don't have an enrollment for the next school year
            $enrollmentIdsToDeactivate = StudentEnrollment::query()
                ->active()
                ->whereHas('student', function (Builder $query) use ($nextSchoolYear) {
                    $query->whereDoesntHave('enrollments', function (Builder $query) use ($nextSchoolYear) {
                        $query->where('school_year_id', $nextSchoolYear->getKey())
                            ->where('status', StudentEnrollmentStatusEnum::ENROLLED);
                    });
                })
                ->pluck('id');

            StudentEnrollment::whereIn('id', $enrollmentIdsToDeactivate)
                ->update(['status' => StudentEnrollmentStatusEnum::INACTIVE]);

            // Activate next school year (deactivates current year).
            // AcademicPeriod::saved re-syncs all students' is_active,
            // covering the bulk update above that bypasses model events.
            $nextSchoolYear->activateExclusively();

            return $processed;
        });
    }
}
