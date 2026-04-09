<?php

declare(strict_types=1);

use App\Enums\StudentEnrollmentStatusEnum;
use App\Filament\Finance\Resources\Students\Pages\EditStudent;
use App\Filament\Finance\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Livewire\Livewire;

beforeEach(function () {
    $this->branch = $this->loginFinance();
});

// ---------------------------------------------------------------------------
// Enrollment lifecycle via the RelationManager
// ---------------------------------------------------------------------------

it('creates enrollment with ENROLLED status when submitted via the relation manager', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callTableAction('create', data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoTableActionErrors();

    // Assert — enrollment was saved with ENROLLED, not DRAFT
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment)
        ->not->toBeNull()
        ->status->toBe(StudentEnrollmentStatusEnum::ENROLLED);
});

it('activates the student after creating enrollment via the relation manager', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callTableAction('create', data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoTableActionErrors();

    // Assert — student is now active
    expect($student->refresh()->is_active)->toBeTrue();
});

it('hides the create enrollment button when student is already active', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'school_year_id' => $activeYear->getKey(),
        'classroom_id' => $classroom->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    $student->refresh(); // is_active is now true

    // Act & Assert
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->assertTableActionHidden('create');
});

it('shows the create enrollment button when student is inactive', function () {
    // Arrange — student with no enrollment
    SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act & Assert
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->assertTableActionVisible('create');
});

it('requires school_year and classroom when creating an enrollment', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act & Assert — pass explicit nulls to override the default() on school_year_id
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callTableAction('create', data: ['school_year_id' => null, 'school_id' => null, 'classroom_id' => null])
        ->assertHasTableActionErrors(['school_year_id' => 'required', 'classroom_id' => 'required']);
});

it('sets branch_id from the Finance tenant when creating enrollment', function () {
    // Arrange
    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callTableAction('create', data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoTableActionErrors();

    // Assert
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment->branch_id)->toBe($this->branch->getKey());
});
