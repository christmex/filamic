<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\Positions\Pages\ManagePositions;
use App\Filament\Admin\Resources\Positions\PositionResource;
use App\Models\Position;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('manage page is accessible', function () {
    // Act & Assert
    $this->get(PositionResource::getUrl())->assertOk();
});

test('manage page renders columns', function (string $column) {
    // Arrange
    Position::factory()->create();

    // Act & Assert
    Livewire::test(ManagePositions::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
]);

test('manage page shows rows', function () {
    // Arrange
    $records = Position::factory(3)->create();

    // Act & Assert
    Livewire::test(ManagePositions::class)
        ->assertCanSeeTableRecords($records);
});

test('can create a record', function () {
    // Act
    Livewire::test(ManagePositions::class)
        ->mountAction('create')
        ->fillForm([
            'name' => 'New Position',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    // Assert
    expect(Position::first())
        ->name->toBe('New Position');
});

test('can edit a record', function () {
    // Arrange
    $record = Position::factory()->create(['name' => 'Old Name']);

    // Act
    Livewire::test(ManagePositions::class)
        ->mountAction(TestAction::make('edit')->table($record))
        ->fillForm([
            'name' => 'Updated Name',
        ])
        ->callMountedAction()
        ->assertHasNoFormErrors();

    // Assert
    expect($record->refresh())
        ->name->toBe('Updated Name');
});

test('can delete a record', function () {
    // Arrange
    $record = Position::factory()->create();

    // Act
    Livewire::test(ManagePositions::class)
        ->callAction(TestAction::make('delete')->table($record));

    // Assert
    expect(Position::count())->toBe(0);
});
