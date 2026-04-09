<?php

declare(strict_types=1);

use App\Enums\GenderEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Filament\Finance\Resources\Students\Pages\CreateStudent;
use App\Filament\Finance\Resources\Students\Pages\EditStudent;
use App\Filament\Finance\Resources\Students\Pages\ListStudents;
use App\Filament\Finance\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Finance\Resources\Students\StudentResource;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Filament\Actions\Testing\TestAction;
use Filament\Forms\Components\Repeater;
use Livewire\Livewire;

beforeEach(function () {
    $this->branch = $this->loginFinance();
});

test('list page is accessible', function () {
    SchoolYear::factory()->active()->create();
    SchoolTerm::factory()->create(['is_active' => true]);

    $this->get(StudentResource::getUrl())->assertOk();
});

test('list page renders student names', function () {
    // Arrange — name is inside a View::make() layout column, so assertSee is used instead of assertCanRenderTableColumn
    $school = School::factory()->for($this->branch)->create();
    Student::factory()->for($school)->create(['name' => 'Visible Student Name']);

    // Act & Assert
    Livewire::test(ListStudents::class)
        ->set('activeTab', 'all')
        ->assertSee('Visible Student Name');
});

test('list page shows rows', function () {
    // Arrange — use the all tab; Student::saved() resets is_active via syncActiveStatus
    $school = School::factory()->for($this->branch)->create();
    $records = Student::factory(3)->for($school)->create();

    // Act & Assert
    Livewire::test(ListStudents::class)
        ->set('activeTab', 'all')
        ->assertCanSeeTableRecords($records);
});

test('can search for records on list page', function () {
    // Arrange — use the all tab; Student::saved() resets is_active via syncActiveStatus
    $school = School::factory()->for($this->branch)->create();
    $record = Student::factory()->for($school)->create(['name' => 'Budi Unique Name']);

    // Act & Assert
    Livewire::test(ListStudents::class)
        ->set('activeTab', 'all')
        ->searchTable('Budi Unique Name')
        ->assertCanSeeTableRecords([$record]);
});

test('create page is accessible', function () {
    SchoolYear::factory()->active()->create();
    SchoolTerm::factory()->create(['is_active' => true]);

    $this->get(StudentResource::getUrl('create'))->assertOk();
});

test('edit page is accessible', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    SchoolTerm::factory()->create(['is_active' => true]);
    $school = School::factory()->for($this->branch)->create();
    $record = Student::factory()->for($school)->create();

    // Act & Assert
    $this->get(StudentResource::getUrl('edit', ['record' => $record]))->assertOk();
});

test('creates enrollment with ENROLLED status when submitted via the relation manager', function () {
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
        ->callAction(TestAction::make('create')->table(), data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoFormErrors();

    // Assert — enrollment was saved with ENROLLED, not DRAFT
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment)
        ->not->toBeNull()
        ->status->toBe(StudentEnrollmentStatusEnum::ENROLLED);
});

test('activates the student after creating enrollment via the relation manager', function () {
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
        ->callAction(TestAction::make('create')->table(), data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoFormErrors();

    // Assert — student is now active
    expect($student->refresh()->is_active)->toBeTrue();
});

test('hides the create enrollment button when student is already active', function () {
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
        ->assertActionHidden(TestAction::make('create')->table());
});

test('shows the create enrollment button when student is inactive', function () {
    // Arrange — student with no enrollment
    SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act & Assert
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->assertActionVisible(TestAction::make('create')->table());
});

test('requires school_year and classroom when creating an enrollment', function () {
    // Arrange
    SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $student = Student::factory()->for($school)->create(['is_active' => false]);

    // Act & Assert — pass explicit nulls to override the default() on school_year_id
    Livewire::test(EnrollmentsRelationManager::class, [
        'ownerRecord' => $student,
        'pageClass' => EditStudent::class,
    ])
        ->callAction(TestAction::make('create')->table(), data: ['school_year_id' => null, 'school_id' => null, 'classroom_id' => null])
        ->assertHasFormErrors(['school_year_id' => 'required', 'classroom_id' => 'required']);
});

test('sets branch_id from the Finance tenant when creating enrollment via the relation manager', function () {
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
        ->callAction(TestAction::make('create')->table(), data: [
            'school_year_id' => $activeYear->getKey(),
            'school_id' => $school->getKey(),
            'classroom_id' => $classroom->getKey(),
        ])
        ->assertHasNoFormErrors();

    // Assert
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment->branch_id)->toBe($this->branch->getKey());
});

test('creates a student and an enrollment together via the create form repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Budi Santoso',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $school->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    $student = Student::where('name', 'Budi Santoso')->first();
    expect($student)->not->toBeNull();

    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();
    expect($enrollment)->not->toBeNull()
        ->classroom_id->toBe($classroom->getKey())
        ->school_year_id->toBe($activeYear->getKey());

    $undoRepeaterFake();
});

test('activates the student when created with an enrollment via the repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Siti Rahma',
            'gender' => GenderEnum::FEMALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $school->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $student = Student::where('name', 'Siti Rahma')->first();
    expect($student->is_active)->toBeTrue();

    $undoRepeaterFake();
});

test('saves enrollment with ENROLLED status when created via the repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Ahmad Fauzi',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $school->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $student = Student::where('name', 'Ahmad Fauzi')->first();
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment->status)->toBe(StudentEnrollmentStatusEnum::ENROLLED);

    $undoRepeaterFake();
});

test('sets branch_id from the Finance tenant when creating enrollment via the repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();

    // Act
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Dewi Lestari',
            'gender' => GenderEnum::FEMALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $school->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $student = Student::where('name', 'Dewi Lestari')->first();
    $enrollment = StudentEnrollment::where('student_id', $student->getKey())->first();

    expect($enrollment->branch_id)->toBe($this->branch->getKey());

    $undoRepeaterFake();
});

test('creates a student with no enrollment when the repeater is left empty', function () {
    $undoRepeaterFake = Repeater::fake();

    // Act
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Tanpa Kelas',
            'gender' => GenderEnum::MALE,
            'enrollments' => [],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert — student created, no enrollment, not active
    $student = Student::where('name', 'Tanpa Kelas')->first();
    expect($student)->not->toBeNull();
    expect(StudentEnrollment::where('student_id', $student->getKey())->exists())->toBeFalse();
    expect($student->is_active)->toBeFalse();

    $undoRepeaterFake();
});

test('fails validation when school_year_id is missing in the enrollment repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $school = School::factory()->for($this->branch)->create();
    $classroom = Classroom::factory()->for($school)->create();

    // Act & Assert
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Test Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => null,
                    'school_id' => $school->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollments.0.school_year_id' => 'required']);

    $undoRepeaterFake();
});

test('fails validation when classroom_id is missing in the enrollment repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $school = School::factory()->for($this->branch)->create();

    // Act & Assert
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Test Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $school->getKey(),
                    'classroom_id' => null,
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollments.0.classroom_id' => 'required']);

    $undoRepeaterFake();
});

test('rejects a classroom that does not belong to the selected school', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $schoolA = School::factory()->for($this->branch)->create();
    $schoolB = School::factory()->for($this->branch)->create();
    $classroomB = Classroom::factory()->for($schoolB)->create(); // belongs to school B

    // Act — try to enroll with school A but classroom from school B
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Test Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $schoolA->getKey(),
                    'classroom_id' => $classroomB->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollments.0.classroom_id']);

    $undoRepeaterFake();
});

test('only schools belonging to the current branch are valid in the enrollment repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $otherBranch = App\Models\Branch::factory()->create();
    $schoolInBranch = School::factory()->for($this->branch)->create(['name' => 'In Branch School']);
    School::factory()->for($otherBranch)->create(['name' => 'Other Branch School']);

    $activeYear = SchoolYear::factory()->active()->create();
    $classroom = Classroom::factory()->for($schoolInBranch)->create();

    // In-branch school — valid
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Scope Test Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $schoolInBranch->getKey(),
                    'classroom_id' => $classroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Other-branch school — invalid
    $outsideClassroom = Classroom::factory()->for(School::factory()->for($otherBranch))->create();

    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Scope Fail Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => School::where('branch_id', $otherBranch->getKey())->value('id'),
                    'classroom_id' => $outsideClassroom->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollments.0.school_id']);

    $undoRepeaterFake();
});

test('only classrooms belonging to the selected school are valid in the enrollment repeater', function () {
    // Arrange
    $undoRepeaterFake = Repeater::fake();

    $activeYear = SchoolYear::factory()->active()->create();
    $schoolA = School::factory()->for($this->branch)->create();
    $schoolB = School::factory()->for($this->branch)->create();
    $classroomA = Classroom::factory()->for($schoolA)->create();
    $classroomB = Classroom::factory()->for($schoolB)->create();

    // School A + classroom A — valid
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Classroom Scope Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $schoolA->getKey(),
                    'classroom_id' => $classroomA->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // School A + classroom B — invalid (belongs to school B)
    Livewire::test(CreateStudent::class)
        ->fillForm([
            'name' => 'Classroom Mismatch Student',
            'gender' => GenderEnum::MALE,
            'enrollments' => [
                [
                    'school_year_id' => $activeYear->getKey(),
                    'school_id' => $schoolA->getKey(),
                    'classroom_id' => $classroomB->getKey(),
                ],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['enrollments.0.classroom_id']);

    $undoRepeaterFake();
});
