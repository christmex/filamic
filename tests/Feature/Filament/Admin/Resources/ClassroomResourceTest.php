<?php

declare(strict_types=1);

use App\Enums\GradeEnum;
use App\Enums\LevelEnum;
use App\Filament\Admin\Resources\Classrooms\ClassroomResource;
use App\Filament\Admin\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Admin\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Admin\Resources\Classrooms\Pages\ListClassrooms;
use App\Models\Classroom;
use App\Models\School;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('list page is accessible', function () {
    // Act & Assert
    $this->get(ClassroomResource::getUrl())->assertOk();
});

test('list page renders columns', function (string $column) {
    // Arrange
    Classroom::factory()->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
    'grade',
    'identifier',
    'phase',
    'is_moving_class',
]);

test('list page shows rows', function () {
    // Arrange
    $records = Classroom::factory(3)->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->assertCanSeeTableRecords($records);
});

test('list page rows have edit action', function () {
    // Arrange
    $record = Classroom::factory()->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->assertActionVisible(TestAction::make('edit')->table($record));
});

test('can search for records on list page', function (string $attribute) {
    // Arrange
    $record = Classroom::factory()->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->searchTable(data_get($record, $attribute))
        ->assertCanSeeTableRecords([$record]);
})->with([
    'name',
]);

test('can filter records by school', function () {
    // Arrange
    $school1 = School::factory()->create();
    $school2 = School::factory()->create();
    $classroom1 = Classroom::factory()->for($school1)->create();
    $classroom2 = Classroom::factory()->for($school2)->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->filterTable('school_id', $school1->getRouteKey())
        ->assertCanSeeTableRecords([$classroom1])
        ->assertCanNotSeeTableRecords([$classroom2]);
});

test('can filter records by moving class', function () {
    // Arrange
    [$movingClassroom, $regularClassroom] = Classroom::factory(2)
        ->sequence(
            ['is_moving_class' => true],
            ['is_moving_class' => false],
        )
        ->create();

    // Act & Assert
    Livewire::test(ListClassrooms::class)
        ->filterTable('is_moving_class', true)
        ->assertCanSeeTableRecords([$movingClassroom])
        ->assertCanNotSeeTableRecords([$regularClassroom]);
});

test('create page is accessible', function () {
    // Act & Assert
    $this->get(ClassroomResource::getUrl('create'))->assertOk();
});

test('cannot create a record without required fields', function () {
    // Act & Assert
    Livewire::test(CreateClassroom::class)
        ->call('create')
        ->assertHasFormErrors([
            'school_id' => 'required',
            'name' => 'required',
            'identifier' => 'required',
        ]);
});

test('cannot create a record with duplicate name', function () {
    // Arrange
    $school = School::factory()->create(['name' => 'School ABC']);
    Classroom::factory()
        ->for($school)
        ->create(['name' => 'Matthew 1']);

    // Act & Assert
    Livewire::test(CreateClassroom::class)
        ->fillForm([
            'school_id' => $school->getKey(),
            'name' => 'Matthew 1',
        ])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('can create a record', function () {
    // Arrange
    $school = School::factory()->create(['level' => LevelEnum::SENIOR_HIGH->value]);

    // Act
    Livewire::test(CreateClassroom::class)
        ->fillForm([
            'school_id' => $school->getKey(),
            'temp_level' => $school->level->value,
            'name' => 'New Test Classroom',
            'grade' => GradeEnum::GRADE_10->value,
            'identifier' => 1,
            'phase' => 'A',
            'is_moving_class' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    expect(Classroom::first())
        ->not->toBeNull()
        ->name->toBe('New Test Classroom')
        ->grade->toBe(GradeEnum::GRADE_10);
});

test('edit page is accessible', function () {
    // Arrange
    $record = Classroom::factory()->create();

    // Act & Assert
    $this->get(ClassroomResource::getUrl('edit', ['record' => $record]))->assertOk();
});

test('cannot save a record without required fields', function () {
    // Arrange
    $record = Classroom::factory()->create();

    // Act & Assert
    Livewire::test(EditClassroom::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'school_id' => null,
            'grade' => null,
            'name' => null,
            'identifier' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'school_id' => 'required',
            'grade' => 'required',
            'name' => 'required',
            'identifier' => 'required',
        ]);
});

test('cannot save a record with duplicate name', function () {
    // Arrange
    $school = School::factory()->create(['name' => 'School ABC']);
    [$record1, $record2] = Classroom::factory(2)
        ->for($school)
        ->sequence(
            ['name' => 'Matthew 1'],
            ['name' => 'Matthew 2'],
        )
        ->create();

    // Act & Assert
    Livewire::test(EditClassroom::class, ['record' => $record1->getRouteKey()])
        ->fillForm([
            'school_id' => $school->getKey(),
            'name' => 'Matthew 2',
        ])
        ->call('save')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('can save a record', function () {
    // Arrange
    $school = School::factory()->create(['level' => LevelEnum::SENIOR_HIGH->value]);
    $record = Classroom::factory()->for($school)->create(['grade' => GradeEnum::GRADE_10->value]);

    // Act
    Livewire::test(EditClassroom::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Classroom',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Assert
    expect($record->refresh())
        ->name->toBe('Updated Classroom');
});

test('can save a record without changes', function () {
    // Arrange
    $school = School::factory()->create(['level' => LevelEnum::SENIOR_HIGH->value]);
    $record = Classroom::factory()->for($school)->create(['grade' => GradeEnum::GRADE_10->value]);

    // Act & Assert
    Livewire::test(EditClassroom::class, ['record' => $record->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
