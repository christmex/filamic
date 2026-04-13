<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Classroom;
use App\Models\Student;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateNextLevelEnrollmentClassroom
{
    use AsAction;

    public function handle(Student $student, Classroom $targetClassroom): void
    {
        $student->nextEnrollment->update([
            'classroom_id' => $targetClassroom->getKey(),
        ]);
    }
}
