<?php

declare(strict_types=1);

use App\Actions\ProcessSchoolYearActivationChunk;
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

it('sets draft enrollment to ENROLLED and old enrollment to PROMOTED for non-final grade', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]); // GRADE_1
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]); // GRADE_2

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($oldClassroom)
        ->create(['is_active' => true]);

    $oldEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $draft = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['processed'])->toBe(1)
        ->and($result['promoted'])->toBe(1)
        ->and($result['stayed'])->toBe(0)
        ->and($result['graduated'])->toBe(0)
        ->and($result['remaining'])->toBe(0)
        ->and($draft->fresh()->status)->toBe(StudentEnrollmentStatusEnum::ENROLLED)
        ->and($oldEnrollment->fresh()->status)->toBe(StudentEnrollmentStatusEnum::PROMOTED);
});

it('sets old enrollment to STAYED when old and new grade are the same', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($classroom)
        ->create(['is_active' => true]);

    $oldEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['stayed'])->toBe(1)
        ->and($result['promoted'])->toBe(0)
        ->and($oldEnrollment->fresh()->status)->toBe(StudentEnrollmentStatusEnum::STAYED);
});

it('sets old enrollment to GRADUATED when old grade is a final year', function () {
    $finalClassroom = Classroom::factory()->for($this->school)->create(['grade' => 10]); // GRADE_6 (final year SD)
    $smpSchool = School::factory()->for($this->branch)->create();
    $smpClassroom = Classroom::factory()->for($smpSchool)->create(['grade' => 11]);

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($finalClassroom)
        ->create(['is_active' => true]);

    $oldEnrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $finalClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $smpSchool->getKey(),
        'classroom_id' => $smpClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['graduated'])->toBe(1)
        ->and($result['promoted'])->toBe(0)
        ->and($oldEnrollment->fresh()->status)->toBe(StudentEnrollmentStatusEnum::GRADUATED);
});

it('marks inactive student draft as ENROLLED but does not count in processed', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    $inactiveStudent = Student::factory()
        ->for($this->branch)->for($this->school)->for($oldClassroom)
        ->create(['is_active' => false]);

    StudentEnrollment::factory()->create([
        'student_id' => $inactiveStudent->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    $draft = StudentEnrollment::factory()->create([
        'student_id' => $inactiveStudent->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    // processed now equals count of all drafts converted to ENROLLED in this chunk,
    // regardless of whether the student had a prior enrollment.
    expect($result['processed'])->toBe(1)
        ->and($result['remaining'])->toBe(0)
        ->and($draft->fresh()->status)->toBe(StudentEnrollmentStatusEnum::ENROLLED);
});

it('returns correct remaining count when there are more drafts than chunk size', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    // Create 55 students with drafts (chunk size is 50)
    $students = Student::factory(55)
        ->for($this->branch)->for($this->school)->for($oldClassroom)
        ->create(['is_active' => true]);

    foreach ($students as $student) {
        StudentEnrollment::factory()->create([
            'student_id' => $student->getKey(),
            'branch_id' => $this->branch->getKey(),
            'school_id' => $this->school->getKey(),
            'classroom_id' => $oldClassroom->getKey(),
            'school_year_id' => $this->activeYear->getKey(),
            'status' => StudentEnrollmentStatusEnum::ENROLLED,
        ]);

        StudentEnrollment::factory()->create([
            'student_id' => $student->getKey(),
            'branch_id' => $this->branch->getKey(),
            'school_id' => $this->school->getKey(),
            'classroom_id' => $newClassroom->getKey(),
            'school_year_id' => $this->nextYear->getKey(),
            'status' => StudentEnrollmentStatusEnum::DRAFT,
        ]);
    }

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['processed'])->toBe(50)
        ->and($result['remaining'])->toBe(5);
});

it('returns zero remaining when all drafts are processed', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($oldClassroom)
        ->create(['is_active' => true]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['remaining'])->toBe(0);
});

it('only processes drafts scoped to the given school year', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $farFutureYear = SchoolYear::factory()->inactive()->create(['start_year' => 2027]);

    $student = Student::factory()
        ->for($this->branch)->for($this->school)->for($classroom)
        ->create(['is_active' => true]);

    // Draft for a different (far future) year — should not be processed
    $untouchedDraft = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $farFutureYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $result = ProcessSchoolYearActivationChunk::run($this->nextYear->getKey());

    expect($result['processed'])->toBe(0)
        ->and($untouchedDraft->fresh()->status)->toBe(StudentEnrollmentStatusEnum::DRAFT);
});
