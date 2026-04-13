<?php

declare(strict_types=1);

use App\Actions\UpdateNextLevelEnrollmentClassroom;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

test('updates the classroom_id on the existing draft enrollment', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $oldClassroom = Classroom::factory()->for($school)->create();
    $newClassroom = Classroom::factory()->for($school)->create();
    $nextYear = SchoolYear::factory()->create(['is_active' => false]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    // Act
    UpdateNextLevelEnrollmentClassroom::run($student, $newClassroom);

    // Assert — new classroom is set, old classroom is gone
    $updatedEnrollment = $student->nextEnrollment()->first();

    expect($updatedEnrollment->classroom_id)
        ->toBe($newClassroom->getKey())         // new classroom applied
        ->not->toBe($oldClassroom->getKey());   // old classroom replaced
});

test('throws when student has no draft enrollment', function () {
    // Arrange — student exists but has no draft (nextEnrollment is null)
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $newClassroom = Classroom::factory()->for($school)->create();

    // No StudentEnrollment created for this student

    // Act & Assert — property access on null must fail loudly, not silently
    expect(fn () => UpdateNextLevelEnrollmentClassroom::run($student, $newClassroom))
        ->toThrow(Error::class, 'Call to a member function update() on null');
});

test('only updates classroom_id, leaving other fields unchanged', function () {
    // Arrange
    $branch = Branch::factory()->create();
    $school = School::factory()->for($branch)->create();
    $student = Student::factory()->for($school)->for($branch)->create();
    $oldClassroom = Classroom::factory()->for($school)->create();
    $newClassroom = Classroom::factory()->for($school)->create();
    $nextYear = SchoolYear::factory()->create(['is_active' => false]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    // Act
    UpdateNextLevelEnrollmentClassroom::run($student, $newClassroom);

    // Assert — status and school_year_id are untouched
    expect($student->nextEnrollment()->first())
        ->status->toBe(StudentEnrollmentStatusEnum::DRAFT)
        ->school_year_id->toBe($nextYear->getKey());
});
