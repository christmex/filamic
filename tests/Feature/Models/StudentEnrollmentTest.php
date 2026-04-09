<?php

declare(strict_types=1);

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

test('it prevents mass assignment to guarded id', function () {
    // Arrange
    $customId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();
    $schoolYear = SchoolYear::factory()->create();

    // Act
    $enrollment = StudentEnrollment::create([
        'id' => $customId,
        'branch_id' => $school->branch_id,
        'school_id' => $school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $schoolYear->getKey(),
        'student_id' => Student::factory()->create()->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED->value,
    ]);

    // Assert
    expect($enrollment->getKey())
        ->toBeString()
        ->not->toBe($customId);
});

test('it casts the columns')
    ->expect(fn () => StudentEnrollment::factory()->create())
    ->status->toBeInstanceOf(StudentEnrollmentStatusEnum::class);

test('active scope returns enrollments for the active school year with active status', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create();

    $activeEnrollment = StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'school_year_id' => $inactiveYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    // Act
    $result = StudentEnrollment::query()->active()->get();

    // Assert
    expect($result)
        ->toHaveCount(1)
        ->first()->getKey()->toBe($activeEnrollment->getKey());
});

test('active scope returns nothing when no active school year exists', function () {
    // Arrange
    SchoolYear::factory()->inactive()->create();
    StudentEnrollment::factory()->create([
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act
    $result = StudentEnrollment::query()->active()->get();

    // Assert
    expect($result)->toBeEmpty();
});

test('inactive scope returns enrollments not in active year or with inactive status', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create();

    StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $inactiveByYear = StudentEnrollment::factory()->create([
        'school_year_id' => $inactiveYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $inactiveByStatus = StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    // Act
    $result = StudentEnrollment::query()->inactive()->get();

    // Assert
    expect($result)
        ->toHaveCount(2)
        ->pluck('id')->toContain($inactiveByYear->getKey(), $inactiveByStatus->getKey());
});

test('isActive returns true only when enrollment matches active year and has active status', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();

    $activeEnrollment = StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $inactiveEnrollment = StudentEnrollment::factory()->create([
        'school_year_id' => $activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    // Act & Assert
    expect($activeEnrollment->isActive())->toBeTrue()
        ->and($inactiveEnrollment->isActive())->toBeFalse();
});

test('it belongs to a school year', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'school_year_id' => $schoolYear->getKey(),
    ]);

    // Act & Assert
    expect($enrollment->schoolYear)
        ->toBeInstanceOf(SchoolYear::class)
        ->getKey()->toBe($schoolYear->getKey());
});

test('it belongs to a classroom', function () {
    // Arrange
    $classroom = Classroom::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
    ]);

    // Act & Assert
    expect($enrollment->classroom)
        ->toBeInstanceOf(Classroom::class)
        ->getKey()->toBe($classroom->getKey());
});

test('it belongs to a school', function () {
    // Arrange
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();
    $enrollment = StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
    ]);

    // Act & Assert
    expect($enrollment->school)
        ->toBeInstanceOf(School::class)
        ->getKey()->toBe($school->getKey());
});

test('it belongs to a branch', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $classroom = Classroom::factory()->for($school)->create();
    $enrollment = StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
    ]);

    // Act & Assert
    expect($enrollment->branch)
        ->toBeInstanceOf(Branch::class)
        ->getKey()->toBe($branch->getKey());
});

test('it belongs to a student', function () {
    // Arrange
    $student = Student::factory()->create();
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
    ]);

    // Act & Assert
    expect($enrollment->student)
        ->toBeInstanceOf(Student::class)
        ->getKey()->toBe($student->getKey());
});

it('saved hook activates student when ENROLLED enrollment is created in active year', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'classroom_id' => $classroom->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Assert
    expect($student->refresh()->is_active)->toBeTrue();
});

it('saved hook keeps student inactive when enrollment is created with DRAFT status', function () {
    // Arrange — DRAFT is status=1, the DB default; it must NOT activate the student
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'classroom_id' => $classroom->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    // Assert
    expect($student->refresh()->is_active)->toBeFalse();
});

it('saved hook deactivates student when active enrollment is changed to inactive status', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);
    $classroom = Classroom::factory()->for($school)->create();

    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'classroom_id' => $classroom->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    expect($student->refresh()->is_active)->toBeTrue(); // precondition

    // Act
    $enrollment->update(['status' => StudentEnrollmentStatusEnum::INACTIVE]);

    // Assert
    expect($student->refresh()->is_active)->toBeFalse();
});

it('saved hook syncs student branch, school and classroom from enrollment', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'classroom_id' => $classroom->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Assert
    $student->refresh();
    expect($student->classroom_id)->toBe($classroom->getKey())
        ->and($student->school_id)->toBe($school->getKey())
        ->and($student->branch_id)->toBe($school->branch_id);
});
