<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessSchoolYearActivationChunk
{
    use AsAction;

    private const CHUNK_SIZE = 50;

    /**
     * @return array{processed: int, promoted: int, stayed: int, graduated: int, remaining: int}
     */
    public function handle(string $schoolYearId): array
    {
        $activeYearId = SchoolYear::getActive()?->getKey();

        $drafts = StudentEnrollment::draft()
            ->where('school_year_id', $schoolYearId)
            ->with('classroom')
            ->limit(self::CHUNK_SIZE)
            ->get();

        // Load the outgoing year's enrollment for each student in one query, keyed by student_id.
        // Any status except INACTIVE/DRAFT is included so re-running after a partial run
        // (where enrollments were already marked PROMOTED/STAYED/GRADUATED) still counts correctly.
        $previousEnrollments = StudentEnrollment::query()
            ->whereIn('student_id', $drafts->pluck('student_id'))
            ->where('school_year_id', $activeYearId ?? '')
            ->whereNotIn('status', [
                StudentEnrollmentStatusEnum::INACTIVE,
                StudentEnrollmentStatusEnum::DRAFT,
            ])
            ->with('classroom')
            ->get()
            ->keyBy('student_id');

        $enrolledIds = [];
        $promotedIds = [];
        $stayedIds = [];
        $graduatedIds = [];
        $processed = 0;

        foreach ($drafts as $draft) {
            $enrolledIds[] = $draft->getKey();

            // Null means the student has no prior active enrollment — newly enrolled or inactive.
            $previousEnrollment = $previousEnrollments->get($draft->student_id);

            if ($previousEnrollment === null) {
                continue;
            }

            $oldGrade = $previousEnrollment->classroom->grade;

            if ($oldGrade === $draft->classroom->grade) {
                $stayedIds[] = $previousEnrollment->getKey();
            } elseif ($oldGrade->isFinalYear()) {
                $graduatedIds[] = $previousEnrollment->getKey();
            } else {
                $promotedIds[] = $previousEnrollment->getKey();
            }

            $processed++;
        }

        DB::transaction(function () use ($enrolledIds, $promotedIds, $stayedIds, $graduatedIds): void {
            if (! empty($enrolledIds)) {
                StudentEnrollment::whereIn('id', $enrolledIds)
                    ->update(['status' => StudentEnrollmentStatusEnum::ENROLLED]);
            }

            if (! empty($promotedIds)) {
                StudentEnrollment::whereIn('id', $promotedIds)
                    ->update(['status' => StudentEnrollmentStatusEnum::PROMOTED]);
            }

            if (! empty($stayedIds)) {
                StudentEnrollment::whereIn('id', $stayedIds)
                    ->update(['status' => StudentEnrollmentStatusEnum::STAYED]);
            }

            if (! empty($graduatedIds)) {
                StudentEnrollment::whereIn('id', $graduatedIds)
                    ->update(['status' => StudentEnrollmentStatusEnum::GRADUATED]);
            }
        });

        $remaining = StudentEnrollment::draft()
            ->where('school_year_id', $schoolYearId)
            ->count();

        return [
            'processed' => $processed,
            'promoted' => count($promotedIds),
            'stayed' => count($stayedIds),
            'graduated' => count($graduatedIds),
            'remaining' => $remaining,
        ];
    }
}
