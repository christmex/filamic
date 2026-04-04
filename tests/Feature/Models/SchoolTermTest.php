<?php

declare(strict_types=1);

use App\Enums\SchoolTermEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;

test('it prevents mass assignment to guarded id', function () {
    // ARRANGE
    $customId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    // ACT
    $schoolTerm = SchoolTerm::create([
        'id' => $customId,
        'name' => SchoolTermEnum::ODD,
    ]);

    // ASSERT
    expect($schoolTerm->getKey())
        ->toBeString()
        ->not->toBe($customId);
});

test('it casts the columns')
    ->expect(fn () => SchoolTerm::factory()->create())
    ->name->toBeInstanceOf(SchoolTermEnum::class)
    ->is_active->toBeBool();

test('it returns allowed months from enum', function () {
    // Arrange
    $oddTerm = SchoolTerm::factory()->odd()->create();
    $evenTerm = SchoolTerm::factory()->even()->create();

    // Act
    $oddMonths = $oddTerm->getAllowedMonths();
    $evenMonths = $evenTerm->getAllowedMonths();

    // Assert
    expect($oddMonths)
        ->toBe([7, 8, 9, 10, 11, 12, 1, 2, 3, 4, 5, 6])
        ->and($evenMonths)->toBe([1, 2, 3, 4, 5, 6]);
});

test('active scope only returns active records', function () {
    // Arrange
    $activeTerm = SchoolTerm::factory()->odd()->active()->create();
    SchoolTerm::factory()->even()->inactive()->create();

    // Act
    $activeTerms = SchoolTerm::query()->active()->get();

    // Assert
    expect($activeTerms)
        ->toHaveCount(1)
        ->first()->getKey()->toBe($activeTerm->getKey());
});

test('inactive scope only returns inactive records', function () {
    // Arrange
    SchoolTerm::factory()->odd()->active()->create();
    $inactiveTerm = SchoolTerm::factory()->even()->inactive()->create();

    // Act
    $inactiveTerms = SchoolTerm::query()->inactive()->get();

    // Assert
    expect($inactiveTerms)
        ->toHaveCount(1)
        ->first()->getKey()->toBe($inactiveTerm->getKey());
});

test('isActive and isInactive reflect current state', function () {
    // Arrange
    $activeTerm = SchoolTerm::factory()->odd()->active()->create();
    $inactiveTerm = SchoolTerm::factory()->even()->inactive()->create();

    // Act & Assert
    expect($activeTerm->isActive())
        ->toBeTrue()
        ->and($activeTerm->isInactive())->toBeFalse()
        ->and($inactiveTerm->isActive())->toBeFalse()
        ->and($inactiveTerm->isInactive())->toBeTrue();
});

test('deactivateOthers turns active records inactive', function () {
    // Arrange
    $first = SchoolTerm::factory()->odd()->active()->create();
    $second = SchoolTerm::factory()->even()->active()->create();

    // Act
    SchoolTerm::deactivateOthers();

    // Assert
    expect($first->refresh()->is_active)
        ->toBeFalse()
        ->and($second->refresh()->is_active)->toBeFalse()
        ->and(SchoolTerm::query()->active()->count())->toBe(0);
});

test('activateExclusively activates current record and deactivates others', function () {
    // Arrange
    $first = SchoolTerm::factory()->odd()->active()->create();
    $second = SchoolTerm::factory()->even()->inactive()->create();

    // Act
    $second->activateExclusively();

    // Assert
    expect($first->refresh()->is_active)
        ->toBeFalse()
        ->and($second->refresh()->is_active)->toBeTrue()
        ->and(SchoolTerm::query()->active()->count())->toBe(1);
});

test('it does not sync students if is_active did not change', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $schoolTerm = SchoolTerm::factory()->active()->create();
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();

    $student = Student::factory()->for($school)
        ->has(
            StudentEnrollment::factory()
                ->state([
                    'school_year_id' => $schoolYear->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ])
                ->enrolled(), 'enrollments')
        ->active()
        ->create();

    // Act: Update something OTHER than is_active
    $schoolTerm->update(['updated_at' => now()->addMinute()]);

    // Assert: Student should remain active
    expect($student->fresh()->is_active)->toBeTrue();
});

test('it keeps students active when school term becomes inactive but school year remains active', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $schoolTerm = SchoolTerm::factory()->active()->create();
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();

    $student = Student::factory()->for($school)->active()->create();
    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $schoolYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act: Deactivate the school term (school year still active)
    $schoolTerm->update(['is_active' => false]);

    // Assert: Student stays active because enrollment is tied to school year
    expect($student->fresh()->is_active)->toBeTrue();
});

test('it syncs student active status when academic period becomes active', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $schoolTerm = SchoolTerm::factory()->inactive()->create();
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();

    $student = Student::factory()->for($school)->inactive()->create();
    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $schoolYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act: Activate the school term
    $schoolTerm->update(['is_active' => true]);

    // Assert: Student should be activated (has enrollment + payment account)
    expect($student->fresh()->is_active)->toBeTrue();
});

test('it clears stale cache when academic period is_active changes', function () {
    // Arrange
    $staleTerm = SchoolTerm::factory()->odd()->inactive()->create();
    cache()->put(SchoolTerm::getActiveCacheKey(), $staleTerm);

    $newTerm = SchoolTerm::factory()->even()->inactive()->create();

    // Act: Activate the new term
    $newTerm->update(['is_active' => true]);

    // Assert: getActive() should return the new term (re-populates cache)
    $active = SchoolTerm::getActive();
    expect($active)
        ->not->toBeNull()
        ->getKey()->toBe($newTerm->getKey());
});

test('getActive returns currently active term', function () {
    // Arrange
    $inactive = SchoolTerm::factory()->odd()->inactive()->create();
    $active = SchoolTerm::factory()->even()->active()->create();

    // Act
    $result = SchoolTerm::getActive();

    // Assert
    expect($result)
        ->not->toBeNull()
        ->getKey()->toBe($active->getKey())
        ->and($result?->getKey())->not->toBe($inactive->getKey());
});

test('getActiveCacheKey returns cache key')
    ->expect(SchoolTerm::getActiveCacheKey())
    ->toBe('active_school_term_record');
