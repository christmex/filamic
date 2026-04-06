<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Branches\BranchResource;
use App\Filament\Admin\Resources\Branches\Pages\ManageBranches;
use App\Models\Branch;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('list page is accessible', function () {
    // Act & Assert
    $this->get(BranchResource::getUrl())->assertOk();
});

test('list page renders columns', function (string $column) {
    // Arrange
    Branch::factory()->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
    'whatsapp',
    'phone',
]);

test('list page renders description for name column', function () {
    // Arrange
    $branch = Branch::factory()->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->assertTableColumnHasDescription('name', $branch->address, $branch);
});

test('list page shows rows', function () {
    // Arrange
    $records = Branch::factory(3)->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->assertCanSeeTableRecords($records);
});

test('list page rows have edit action', function () {
    // Arrange
    $record = Branch::factory()->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->assertActionVisible(TestAction::make('edit')->table($record));
});

test('cannot create a record without required fields', function () {
    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->callAction('create')
        ->assertHasFormErrors(['name' => 'required']);
});

test('cannot create a record with duplicate name', function () {
    // Arrange
    Branch::factory()->create(['name' => 'School ABC']);

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->callAction('create', data: [
            'name' => 'School ABC',
        ])
        ->assertHasFormErrors(['name' => ['unique']]);
});

test('can create a record', function () {
    // Arrange
    $data = Branch::factory()->make([
        'name' => 'New Test School',
    ])->toArray();

    // Act
    Livewire::test(ManageBranches::class)
        ->callAction('create', $data)
        ->assertHasNoFormErrors();

    // Assert
    expect(Branch::latest('id')->first())
        ->name->toBe('New Test School');
});

test('cannot save a record without required fields', function () {
    // Arrange
    $record = Branch::factory()->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->fillForm([
            'name' => null,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['name' => 'required']);
});

test('cannot save a record with duplicate name', function () {
    // Arrange
    [$branchA, $branchB] = Branch::factory(2)
        ->forEachSequence(
            ['name' => 'Branch A'],
            ['name' => 'Branch B'],
        )
        ->create();

    // Act & Assert
    Livewire::test(ManageBranches::class)
        ->mountAction(TestAction::make('edit')->table($branchA))
        ->fillForm([
            'name' => $branchB->name,
        ])
        ->callMountedAction()
        ->assertHasFormErrors(['name' => 'unique']);
});

test('can save a record', function () {
    $record = Branch::factory()->create();

    $newData = Branch::factory()->make([
        'name' => 'Updated Branch Name',
        'address' => 'Updated Address',
    ]);

    Livewire::test(ManageBranches::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->fillForm($newData->toArray())
        ->callMountedAction()
        ->assertHasNoFormErrors();

    expect($record->refresh())
        ->name->toBe('Updated Branch Name')
        ->address->toBe('Updated Address');
});

test('can save a record without changes', function () {
    $record = Branch::factory()->create();

    Livewire::test(ManageBranches::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->callMountedAction()
        ->assertHasNoFormErrors();
});
