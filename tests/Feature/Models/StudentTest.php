<?php

declare(strict_types=1);

use App\Enums\GenderEnum;
use App\Enums\ReligionEnum;
use App\Enums\StatusInFamilyEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

it('casts the columns')
    ->expect(fn () => Student::factory()->create())
    ->gender->toBeInstanceOf(GenderEnum::class)
    ->status_in_family->toBeInstanceOf(StatusInFamilyEnum::class)
    ->religion->toBeInstanceOf(ReligionEnum::class);

it('syncActiveStatus activates student when active enrollment and monthly fee data exist', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create([
        'is_active' => false,
        'monthly_fee_virtual_account' => '12345678',
        'monthly_fee_amount' => 250_000,
    ]);
    $classroom = Classroom::factory()->for($school)->create();

    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act
    $student->syncActiveStatus();

    // Assert
    expect($student->refresh()->is_active)->toBeTrue();
});

it('syncActiveStatus deactivates student when no active enrollment exists', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    $student = Student::factory()->create(['is_active' => true]);

    // Act — no active enrollment
    $student->syncActiveStatus();

    // Assert
    expect($student->refresh()->is_active)->toBeFalse();
});

it('syncActiveStatus deactivates student when enrollment exists but no monthly fee data', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->withoutMonthlyFee()->create(['is_active' => true]);
    $classroom = Classroom::factory()->for($school)->create();

    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act — no monthly fee data
    $student->syncActiveStatus();

    // Assert
    expect($student->refresh()->is_active)->toBeFalse();
});

it('syncActiveStatus deactivates student when no active school year exists', function () {
    // Arrange
    SchoolYear::factory()->inactive()->create();
    $student = Student::factory()->create(['is_active' => true]);

    // Act
    $student->syncActiveStatus();

    // Assert — no active year means no currentEnrollment
    expect($student->refresh()->is_active)->toBeFalse();
});

it('unpaidMonthlyFee returns unpaid monthly fee invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    $unpaid = Invoice::factory()->for($student)->monthlyFee()->unpaid()->create();
    Invoice::factory()->for($student)->monthlyFee()->paid()->create();

    // Act
    $result = $student->unpaidMonthlyFee;

    // Assert
    expect($result)
        ->toHaveCount(1)
        ->first()->getKey()->toBe($unpaid->getKey());
});

it('paidMonthlyFee returns paid monthly fee invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create();
    $paid = Invoice::factory()->for($student)->monthlyFee()->paid()->create();

    // Act
    $result = $student->paidMonthlyFee;

    // Assert
    expect($result)
        ->toHaveCount(1)
        ->first()->getKey()->toBe($paid->getKey());
});

it('currentEnrollment returns only active enrollment', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $student = Student::factory()->for($school)->create();
    $classroom = Classroom::factory()->for($school)->create();

    $activeEnrollment = StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => SchoolYear::factory()->inactive(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    // Act
    $student->refresh();
    $current = $student->currentEnrollment;

    // Assert
    expect($current)
        ->not->toBeNull()
        ->getKey()->toBe($activeEnrollment->getKey());
});

it('currentEnrollment returns null when no active enrollment exists', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    $student = Student::factory()->create();

    // Act
    $current = $student->currentEnrollment;

    // Assert
    expect($current)->toBeNull();
});
