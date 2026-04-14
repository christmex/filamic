<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateNextLevelEnrollment
{
    use AsAction;

    public function handle(Student $student, Classroom $targetClassroom): StudentEnrollment
    {
        $nextSchoolYear = SchoolYear::getNextSchoolYearOrFail();

        return DB::transaction(fn () => $student->enrollments()->create([
            'branch_id' => $student->branch_id,
            'school_id' => $targetClassroom->school_id,
            'classroom_id' => $targetClassroom->getKey(),
            'school_year_id' => $nextSchoolYear->getKey(),
            'status' => StudentEnrollmentStatusEnum::DRAFT,
        ]));
    }
}
