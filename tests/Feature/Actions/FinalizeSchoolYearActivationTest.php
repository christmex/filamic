<?php

declare(strict_types=1);

use App\Actions\FinalizeSchoolYearActivation;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->school = School::factory()->for($this->branch)->create();
    $this->activeYear = SchoolYear::factory()->active()->create(['start_year' => 2025]);
    $this->nextYear = SchoolYear::factory()->inactive()->create(['start_year' => 2026]);
});

it('activates the next school year and deactivates the current one', function () {
    FinalizeSchoolYearActivation::run($this->nextYear->getKey());

    expect($this->nextYear->fresh()->is_active)->toBeTrue()
        ->and($this->activeYear->fresh()->is_active)->toBeFalse();
});

it('deactivates ENROLLED enrollments for students without next year enrollment', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($classroom)
        ->create(['is_active' => true]);

    $currentEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $result = FinalizeSchoolYearActivation::run($this->nextYear->getKey());

    expect($result['deactivated'])->toBe(1)
        ->and($currentEnrollment->fresh()->status)->toBe(StudentEnrollmentStatusEnum::INACTIVE);
});

it('does not deactivate enrollments for students who have an ENROLLED status in the next year', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($oldClassroom)
        ->create(['is_active' => true]);

    $currentEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Student already has ENROLLED status for the next year (chunk already processed them)
    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $result = FinalizeSchoolYearActivation::run($this->nextYear->getKey());

    expect($result['deactivated'])->toBe(0)
        ->and($currentEnrollment->fresh()->status)->toBe(StudentEnrollmentStatusEnum::ENROLLED);
});

it('returns correct deactivated count', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    [$studentA, $studentB, $studentC] = Student::factory(3)
        ->for($this->branch)->for($this->school)->for($classroom)
        ->create(['is_active' => true]);

    // All three have current enrollments
    foreach ([$studentA, $studentB, $studentC] as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => $student->getKey(),
            'branch_id' => $this->branch->getKey(),
            'school_id' => $this->school->getKey(),
            'classroom_id' => $classroom->getKey(),
            'school_year_id' => $this->activeYear->getKey(),
            'status' => StudentEnrollmentStatusEnum::ENROLLED,
        ]);
    }

    // Only studentC has next-year enrollment (chunk processed them)
    StudentEnrollment::factory()->create([
        'student_id' => $studentC->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $result = FinalizeSchoolYearActivation::run($this->nextYear->getKey());

    expect($result['deactivated'])->toBe(2);
});
