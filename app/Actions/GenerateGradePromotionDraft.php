<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Exception;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GenerateGradePromotionDraft
{
    use AsAction;

    public function handle(): int
    {
        $nextSchoolYear = SchoolYear::getNextSchoolYearOrFail();

        $students = Student::query()
            ->active()
            ->notInFinalYears()
            ->doesntHaveDraftEnrollmentForNextSchoolYear()
            ->with(['classroom'])
            ->get();

        $drafts = [];

        $schoolIds = $students->pluck('school_id')->unique()->values();
        $grades = $students->pluck('classroom.grade')->unique()->map(fn ($grade) => $grade->value + 1)->values();

        $classroomsOptions = Classroom::query()
            ->whereIn('school_id', $schoolIds)
            ->whereIn('grade', $grades)
            ->get();

        foreach ($students as $student) {
            $nextClassroomSuggestion = null;

            if (! $student->classroom->grade->isManualPromotionGrade()) {

                $nextClassroomSuggestion = $classroomsOptions
                    ->where('school_id', $student->school_id)
                    ->where('grade', $student->classroom->getRawOriginal('grade') + 1)
                    ->where('identifier', $student->classroom->identifier)
                    ->first();
            }

            $drafts[] = [
                'branch_id' => $student->branch_id,
                'school_id' => $student->school_id,
                'classroom_id' => $nextClassroomSuggestion?->getKey(),
                'school_year_id' => $nextSchoolYear->getKey(),
                'student_id' => $student->getKey(),
                'status' => StudentEnrollmentStatusEnum::DRAFT,
            ];
        }

        if (blank($drafts)) {
            throw new Exception('Tidak Ada Siswa Yang Perlu Dibuatkan Draft');
        }

        return DB::transaction(function () use ($drafts) {
            foreach (array_chunk($drafts, 500) as $draft) {
                StudentEnrollment::fillAndInsert($draft);
            }

            return count($drafts);
        });
    }
}
