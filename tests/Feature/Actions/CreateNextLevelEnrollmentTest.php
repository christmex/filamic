<?php

declare(strict_types=1);

use App\Actions\CreateNextLevelEnrollment;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

test('creates a DRAFT enrollment using the classroom school_id, not the student school_id', function () {
    // Arrange — student is in SD (sdSchool), target classroom is in SMP (smpSchool), same branch
    $branch = Branch::factory()->create();
    $sdSchool = School::factory()->for($branch)->create();
    $smpSchool = School::factory()->for($branch)->create(); // different school_id, same branch
    $student = Student::factory()->for($sdSchool)->for($branch)->create();
    $targetClassroom = Classroom::factory()->for($smpSchool)->create();

    SchoolYear::factory()->active()->create(['start_year' => 2025]);
    $nextYear = SchoolYear::factory()->create(['start_year' => 2026, 'is_active' => false]);

    // Act
    $enrollment = CreateNextLevelEnrollment::run($student, $targetClassroom);

    // Assert — school_id must come from the classroom, not the student
    expect($enrollment)
        ->toBeInstanceOf(StudentEnrollment::class)
        ->school_id->toBe($smpSchool->getKey())     // would FAIL if bug still used $student->school_id
        ->branch_id->toBe($branch->getKey())
        ->classroom_id->toBe($targetClassroom->getKey())
        ->school_year_id->toBe($nextYear->getKey())
        ->status->toBe(StudentEnrollmentStatusEnum::DRAFT);

    // Confirm the old SD school_id is NOT used — separate assertion to prove the test is breakable
    expect($enrollment->school_id)->not->toBe($sdSchool->getKey());
});

test('persists the enrollment to the database', function () {
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $targetClassroom = Classroom::factory()->for($school)->create();

    SchoolYear::factory()->active()->create(['start_year' => 2025]);
    SchoolYear::factory()->create(['start_year' => 2026, 'is_active' => false]);

    CreateNextLevelEnrollment::run($student, $targetClassroom);

    expect(StudentEnrollment::where('student_id', $student->getKey())
        ->where('status', StudentEnrollmentStatusEnum::DRAFT)
        ->exists()
    )->toBeTrue();
});

test('throws when no next school year exists', function () {
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $targetClassroom = Classroom::factory()->for($school)->create();

    SchoolYear::factory()->active()->create(['start_year' => 2025]);
    // No next school year created

    expect(fn () => CreateNextLevelEnrollment::run($student, $targetClassroom))
        ->toThrow(InvalidArgumentException::class, 'Tahun Ajaran Selanjutnya Tidak Ditemukan');
});

test('throws when no active school year exists', function () {
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $targetClassroom = Classroom::factory()->for($school)->create();

    // No active school year at all

    expect(fn () => CreateNextLevelEnrollment::run($student, $targetClassroom))
        ->toThrow(InvalidArgumentException::class, 'Tahun Ajaran Selanjutnya Tidak Ditemukan');
});
