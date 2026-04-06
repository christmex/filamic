<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Schools\Pages\CreateSchool;
use App\Filament\Admin\Resources\Schools\Pages\EditSchool;
use App\Filament\Admin\Resources\Schools\Pages\ListSchools;
use App\Filament\Admin\Resources\Schools\SchoolResource;
use App\Models\School;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('list page is accessible', function () {
    // Act & Assert
    $this->get(SchoolResource::getUrl())->assertOk();
});

test('list page renders columns', function (string $column) {
    // Arrange
    School::factory()->create();

    // Act & Assert
    Livewire::test(ListSchools::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
    'level',
]);

test('list page shows rows', function () {
    // Arrange
    $records = School::factory(3)->create();

    // Act & Assert
    Livewire::test(ListSchools::class)
        ->assertCanSeeTableRecords($records);
});

test('list page rows have edit action', function () {
    // Arrange
    $record = School::factory()->create();

    // Act & Assert
    Livewire::test(ListSchools::class)
        ->assertActionVisible(TestAction::make('edit')->table($record));
});

test('create page is accessible', function () {
    // Act & Assert
    $this->get(SchoolResource::getUrl('create'))->assertOk();
});

test('cannot create a record without required fields', function () {
    // Act & Assert
    Livewire::test(CreateSchool::class)
        ->call('create')
        ->assertHasFormErrors([
            'branch_id' => 'required',
            'name' => 'required',
            'level' => 'required',
        ]);
});

test('cannot create a record with duplicate name', function () {
    // Arrange
    $data = School::factory()->create(['name' => 'School ABC']);

    // Act & Assert
    Livewire::test(CreateSchool::class)
        ->fillForm(['name' => 'School ABC'])
        ->call('create')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('can create a record', function () {
    // Arrange
    $data = School::factory()->make([
        'name' => 'New Test School',
    ]);

    // Act
    Livewire::test(CreateSchool::class)
        ->fillForm($data->toArray())
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    expect(School::latest('id')->first())
        ->name->toBe('New Test School');
});

test('edit page is accessible', function () {
    // Arrange
    $record = School::factory()->create();

    // Act & Assert
    $this->get(SchoolResource::getUrl('edit', ['record' => $record]))->assertOk();
});

test('cannot save a record without required fields', function () {
    // Arrange
    $record = School::factory()->create();

    // Act & Assert
    Livewire::test(EditSchool::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'branch_id' => null,
            'name' => null,
            'level' => null,
        ])
        ->call('save')
        ->assertHasFormErrors([
            'branch_id' => 'required',
            'name' => 'required',
            'level' => 'required',
        ]);
});

test('cannot save a record with duplicate name', function () {
    // Arrange
    [$schoolA, $schoolB] = School::factory(2)
        ->forEachSequence(
            ['name' => 'School A'],
            ['name' => 'School B'],
        )
        ->create();

    // Act & Assert
    Livewire::test(EditSchool::class, ['record' => $schoolA->getRouteKey()])
        ->fillForm(['name' => $schoolB->name])
        ->call('save')
        ->assertHasFormErrors(['name' => 'unique']);
});

test('can save a record', function () {
    // Arrange
    $record = School::factory()->create();

    $newData = School::factory()->make([
        'branch_id' => $record->branch_id,
        'name' => 'Updated School Name',
        'address' => 'Updated Address',
    ]);

    // Act & Assert
    Livewire::test(EditSchool::class, ['record' => $record->getRouteKey()])
        ->fillForm($newData->toArray())
        ->call('save')
        ->assertHasNoFormErrors();

    expect($record->refresh())
        ->name->toBe('Updated School Name')
        ->address->toBe('Updated Address');
});

test('can save a record without changes', function () {
    $record = School::factory()->create();

    Livewire::test(EditSchool::class, ['record' => $record->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
