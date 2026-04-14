<?php

declare(strict_types=1);

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

test('it prevents mass assignment to guarded id', function () {
    // ARRANGE
    $customId = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

    // ACT
    $schoolYear = SchoolYear::create([
        'id' => $customId,
        'start_year' => 2025,
        'end_year' => 2026,
    ]);

    // ASSERT
    expect($schoolYear->getKey())
        ->toBeString()
        ->not->toBe($customId);
});

test('it casts the columns')
    ->expect(fn () => SchoolYear::factory()->create())
    ->start_year->toBeInt()
    ->end_year->toBeInt()
    ->start_date->toBeInstanceOf(Carbon::class)
    ->end_date->toBeInstanceOf(Carbon::class)
    ->is_active->toBeBool();

test('it automatically sets end_year')
    ->expect(fn () => SchoolYear::create(['start_year' => 2025]))
    ->end_year->toBe(2026);

test('it enforces start_date and end_date years correspond to start_year and end_year', function () {
    // Arrange: Create with years that DON'T match (e.g. 2099)
    $schoolYear = SchoolYear::create([
        'start_year' => 2025,
        'start_date' => '2099-07-01',
        'end_date' => '2099-06-30',
    ]);

    // Assert: Model should have forced them back to 2025 and 2026
    expect($schoolYear->start_date->format('Y-m-d'))->toBe('2025-07-01')
        ->and($schoolYear->end_date->format('Y-m-d'))->toBe('2026-06-30');
});

test('it formats name accessor from start_year and end_year', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->make([
        'start_year' => 2025,
        'end_year' => 2026,
    ]);

    // Act
    $name = $schoolYear->name;

    // Assert
    expect($name)->toBe('2025/2026');
});

test('active scope only returns active records', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    SchoolYear::factory()->inactive()->create();

    // Act
    $activeYears = SchoolYear::query()->active()->get();

    // Assert
    expect($activeYears)
        ->toHaveCount(1)
        ->first()->id->toBe($activeYear->getKey());
});

test('inactive scope only returns inactive records', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create();

    // Act
    $inactiveYears = SchoolYear::query()->inactive()->get();

    // Assert
    expect($inactiveYears)
        ->toHaveCount(1)
        ->first()->id->toBe($inactiveYear->getKey());
});

test('isActive and isInactive reflect current state', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create();

    // Act & Assert
    expect($activeYear->isActive())
        ->toBeTrue()
        ->and($activeYear->isInactive())->toBeFalse()
        ->and($inactiveYear->isActive())->toBeFalse()
        ->and($inactiveYear->isInactive())->toBeTrue();
});

test('deactivateOthers turns active records inactive', function () {
    // Arrange
    [$first, $second] = SchoolYear::factory(2)->active()->create();

    // Act
    SchoolYear::deactivateOthers();

    // Assert
    expect($first->refresh()->is_active)
        ->toBeFalse()
        ->and($second->refresh()->is_active)->toBeFalse()
        ->and(SchoolYear::query()->active()->count())->toBe(0);
});

test('activateExclusively activates current record and deactivates others', function () {
    // Arrange
    $first = SchoolYear::factory()->active()->create();
    $second = SchoolYear::factory()->inactive()->create();

    // Act
    $second->activateExclusively();

    // Assert
    expect($first->refresh()->is_active)
        ->toBeFalse()
        ->and($second->refresh()->is_active)->toBeTrue()
        ->and(SchoolYear::query()->active()->count())->toBe(1);
});

test('it enforces start_date and end_date years correspond to start_year and end_year on update', function () {
    // Arrange
    $schoolYear = SchoolYear::create([
        'start_year' => 2025,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);

    // Act: Update start_year to 2026
    $schoolYear->update(['start_year' => 2026]);

    // Assert: Model should have forced they follow 2026/2027
    expect($schoolYear->start_year)->toBe(2026)
        ->and($schoolYear->end_year)->toBe(2027)
        ->and($schoolYear->start_date->format('Y-m-d'))->toBe('2026-07-01')
        ->and($schoolYear->end_date->format('Y-m-d'))->toBe('2027-06-30');
});

test('it syncs end_date when end_year is updated manually', function () {
    // Arrange
    $schoolYear = SchoolYear::create([
        'start_year' => 2025,
        'end_year' => 2026,
        'end_date' => '2026-06-30',
    ]);

    // Act: Manually update end_year (bypassing the automatic +1 just for testing the dirty check)
    $schoolYear->update(['end_year' => 2028]);

    // Assert: end_date should have moved to 2028 in the database
    $schoolYear->refresh();
    expect($schoolYear->end_year)->toBe(2028)
        ->and($schoolYear->end_date->format('Y-m-d'))->toBe('2028-06-30');
});

test('it does not sync students if is_active did not change', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $student = Student::factory()
        ->has(
            StudentEnrollment::factory()
                ->state([
                    'school_year_id' => $schoolYear->getKey(),
                ])
                ->enrolled(), 'enrollments')
        ->active()
        ->create();

    // Act: Update something OTHER than is_active
    $schoolYear->update(['start_year' => $schoolYear->start_year]);

    // Assert: Student should remain active
    expect($student->fresh()->is_active)->toBeTrue();
});

test('it deactivates students when academic period becomes inactive', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();

    $student = Student::factory()->for($school)->active()->create();
    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $schoolYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act: Deactivate the school year
    $schoolYear->update(['is_active' => false]);

    // Assert: Student should be deactivated (no active enrollment anymore)
    expect($student->fresh()->is_active)->toBeFalse();
});

test('it syncs student active status when academic period becomes active', function () {
    // Arrange
    $schoolYear = SchoolYear::factory()->inactive()->create();
    $school = School::factory()->create();
    $classroom = Classroom::factory()->for($school)->create();

    $student = Student::factory()->for($school)->inactive()->create();
    StudentEnrollment::factory()->create([
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $schoolYear->getKey(),
        'student_id' => $student->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Act: Activate the school year
    $schoolYear->update(['is_active' => true]);

    // Assert: Student should be activated (has enrollment + payment account)
    expect($student->fresh()->is_active)->toBeTrue();
});

test('it clears stale cache when academic period is_active changes', function () {
    // Arrange
    $staleYear = SchoolYear::factory()->inactive()->create();
    cache()->put(SchoolYear::getActiveCacheKey(), $staleYear);

    $newYear = SchoolYear::factory()->inactive()->create();

    // Act: Activate the new year
    $newYear->update(['is_active' => true]);

    // Assert: Cache should no longer hold the stale record
    $cached = cache()->get(SchoolYear::getActiveCacheKey());
    expect($cached)
        ->not->toBeNull()
        ->getKey()->toBe($newYear->getKey());
});

test('getActive returns currently active school year', function () {
    // Arrange
    $inactive = SchoolYear::factory()->inactive()->create();
    $active = SchoolYear::factory()->active()->create();

    // Act
    $result = SchoolYear::getActive();

    // Assert
    expect($result)
        ->not->toBeNull()
        ->id->toBe($active->getKey())
        ->and($result->getKey())->not->toBe($inactive->getKey());
});

test('getActiveCacheKey returns cache key')
    ->expect(SchoolYear::getActiveCacheKey())
    ->toBe('active_school_year_record');

test('getNextSchoolYearOrFail returns next school year', function () {
    SchoolYear::factory()->active()->create(['start_year' => 2025]);
    $nextYear = SchoolYear::factory()->inactive()->create(['start_year' => 2026]);

    $result = SchoolYear::getNextSchoolYearOrFail();

    expect($result->getKey())->toBe($nextYear->getKey());
});

test('getNextSchoolYearOrFail throws when no next school year exists', function () {
    SchoolYear::factory()->active()->create(['start_year' => 2025]);

    expect(fn () => SchoolYear::getNextSchoolYearOrFail())
        ->toThrow(ModelNotFoundException::class, 'Tahun Ajaran Selanjutnya Tidak Ditemukan');
});

test('getNextSchoolYearOrFail throws when no active school year exists', function () {
    SchoolYear::factory()->inactive()->create(['start_year' => 2025]);

    expect(fn () => SchoolYear::getNextSchoolYearOrFail())
        ->toThrow(ModelNotFoundException::class, 'Tahun Ajaran Selanjutnya Tidak Ditemukan');
});
