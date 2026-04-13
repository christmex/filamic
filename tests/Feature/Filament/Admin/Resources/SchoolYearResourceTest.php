<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\SchoolYears\Pages\CreateSchoolYear;
use App\Filament\Admin\Resources\SchoolYears\Pages\EditSchoolYear;
use App\Filament\Admin\Resources\SchoolYears\Pages\ListSchoolYears;
use App\Filament\Admin\Resources\SchoolYears\SchoolYearResource;
use App\Models\SchoolYear;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('list page is accessible', function () {
    // Act & Assert
    $this->get(SchoolYearResource::getUrl())->assertOk();
});

test('list page renders columns', function (string $column) {
    // Arrange
    SchoolYear::factory()->create();

    // Act & Assert
    Livewire::test(ListSchoolYears::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
    'is_active',
]);

// it('can toggle is_active column', function () {
//     [$firstSchoolYear, $secondSchoolYear] = SchoolYear::factory(2)->inactive()->create();

//     expect(SchoolYear::query()->active()->count())
//         ->toBe(0);

//     Livewire::test(ListSchoolYears::class)
//         ->call('updateTableColumnState', 'is_active', $firstSchoolYear->getKey(), true);

//     expect(SchoolYear::query()->active()->first())
//         ->is($firstSchoolYear)
//         ->and(SchoolYear::query()->inactive()->first())
//         ->is($secondSchoolYear);

//     Livewire::test(ListSchoolYears::class)
//         ->call('updateTableColumnState', 'is_active', $secondSchoolYear->getKey(), true);

//     expect(SchoolYear::query()->active()->first())
//         ->is($secondSchoolYear)
//         ->and(SchoolYear::query()->inactive()->first())
//         ->is($firstSchoolYear);
// });

test('list page shows rows', function () {
    // Arrange
    $records = SchoolYear::factory(3)->create();

    // Act & Assert
    Livewire::test(ListSchoolYears::class)
        ->assertCanSeeTableRecords($records);
});

test('list page rows does not have edit action for inactive record', function () {
    // Arrange
    $record = SchoolYear::factory()->inactive()->create();

    // Act & Assert
    Livewire::test(ListSchoolYears::class)
        ->assertActionHidden(TestAction::make('edit')->table($record));
});

test('list page rows have edit action for only the active record', function () {
    // Arrange
    $record = SchoolYear::factory()->active()->create();

    // Act & Assert
    Livewire::test(ListSchoolYears::class)
        ->assertActionVisible(TestAction::make('edit')->table($record));
});

test('create page is accessible', function () {
    $this->get(SchoolYearResource::getUrl('create'))->assertOk();
});

test('cannot create a record without required fields', function () {
    Livewire::test(CreateSchoolYear::class)
        ->call('create')
        ->assertHasFormErrors([
            'start_year' => 'required',
        ]);
});

test('cannot create a record with invalid data', function () {
    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 'not-a-number',
            'start_date' => 'not-a-date',
            'end_date' => 'not-a-date',
            'is_active' => 'not-a-boolean',
        ])
        ->call('create')
        ->assertHasFormErrors([
            'start_year' => 'numeric',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ]);
});

test('cannot create a record with duplicate start_year', function () {
    SchoolYear::factory()->create([
        'start_year' => 2025,
        'end_year' => 2026,
    ]);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'start_year' => 'unique',
        ]);
});

test('start_year change in create mode updates calculated fields', function () {
    Livewire::test(CreateSchoolYear::class)
        ->fillForm(['start_year' => 2025])
        ->assertSchemaStateSet([
            'end_year' => 2026,
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);
});

test('end_year is automatically calculated and protected during create', function () {
    // Act: Even if we try to inject a different end_year,
    // it's not dehydrated and the model will overwrite it.
    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'end_year' => 2099, // Injected end_year (should be ignored)
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30', // Valid for 2025 start_year
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert: Database has the correctly calculated end_year
    expect(SchoolYear::query()->where('start_year', 2025)->first())
        ->not->toBeNull()
        ->end_year->toBe(2026)
        ->end_date->format('Y-m-d')->toBe('2026-06-30');
});

test('can create a record', function () {
    // Arrange
    $data = [
        'start_year' => 2025,
        'end_year' => 2026,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
        'is_active' => false,
    ];

    // Act
    Livewire::test(CreateSchoolYear::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    expect(SchoolYear::query()->where('start_year', 2025)->first())
        ->not->toBeNull()
        ->name->toBe('2025/2026');
});

test('can create an active school year', function () {
    // Arrange
    $data = [
        'start_year' => 2024,
        'end_year' => 2025,
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => true,
    ];

    // Act
    Livewire::test(CreateSchoolYear::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    expect(SchoolYear::query()->where('start_year', 2024)->first())
        ->not->toBeNull()
        ->is_active->toBeTrue();
});

test('creating an active school year deactivates others', function () {
    // Arrange
    $existingActive = SchoolYear::factory()->active()->create([
        'start_year' => 2023,
        'end_year' => 2024,
    ]);

    $data = [
        'start_year' => 2024,
        'end_year' => 2025,
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => true,
    ];

    // Act
    Livewire::test(CreateSchoolYear::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    $newActive = SchoolYear::query()->where('start_year', 2024)->first();

    expect($existingActive->refresh()->is_active)
        ->toBeFalse()
        ->and($newActive)->not->toBeNull()
        ->and($newActive->is_active)->toBeTrue()
        ->and(SchoolYear::query()->active()->count())->toBe(1);
});

test('creating an inactive school year does not deactivate others', function () {
    // Arrange
    $existingActive = SchoolYear::factory()->active()->create([
        'start_year' => 2023,
        'end_year' => 2024,
    ]);

    $data = [
        'start_year' => 2024,
        'end_year' => 2025,
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-30',
        'is_active' => false,
    ];

    // Act
    Livewire::test(CreateSchoolYear::class)
        ->fillForm($data)
        ->call('create')
        ->assertHasNoFormErrors();

    // Assert
    $newInactive = SchoolYear::query()->where('start_year', 2024)->first();

    expect($existingActive->refresh()->is_active)
        ->toBeTrue()
        ->and($newInactive)->not->toBeNull()
        ->and($newInactive->is_active)->toBeFalse()
        ->and(SchoolYear::query()->active()->count())->toBe(1);
});

test('edit page is accessible', function () {
    $record = SchoolYear::factory()->create();

    $this->get(SchoolYearResource::getUrl('edit', ['record' => $record]))->assertOk();
});

test('cannot save a record when end_date is before start_date', function () {
    $record = SchoolYear::factory()->create();

    Livewire::test(EditSchoolYear::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'start_date' => '2024-06-01',
            'end_date' => '2024-01-01',
        ])
        ->call('save')
        ->assertHasFormErrors(['end_date']);
});

test('start_year change in edit mode does not update calculated fields', function () {
    // Arrange
    $record = SchoolYear::factory()->create([
        'start_year' => 2025,
        'end_year' => 2026,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);

    // Act & Assert
    Livewire::test(EditSchoolYear::class, ['record' => $record->getRouteKey()])
        ->fillForm(['start_year' => 2030])
        ->assertSchemaStateSet([
            'end_year' => 2026,
            'start_date' => '2025-07-01',
            'end_date' => '2026-06-30',
        ]);
});

test('start_year cannot be updated in edit mode', function () {
    // Arrange
    $record = SchoolYear::factory()->create([
        'start_year' => 2025,
        'end_year' => 2026,
        'start_date' => '2025-07-01',
        'end_date' => '2026-06-30',
    ]);

    // Act
    Livewire::test(EditSchoolYear::class, ['record' => $record->getRouteKey()])
        ->assertFormFieldDisabled('start_year')
        ->fillForm([
            'start_date' => '2025-07-10', // Valid for actual start_year 2025
            'end_date' => '2026-06-10',   // Valid for actual end_year 2026
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Assert: Database still has the old start_year
    expect($record->refresh()->start_year)->toBe(2025);
});

test('start_date must be in July of start_year', function () {
    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'start_date' => '2025-06-30', // Before July
        ])
        ->call('create')
        ->assertHasFormErrors(['start_date']);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'start_date' => '2025-08-01', // After July
        ])
        ->call('create')
        ->assertHasFormErrors(['start_date']);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'start_date' => '2025-07-15', // Correct
        ])
        ->call('create')
        ->assertHasNoFormErrors(['start_date']);
});

test('end_date must be in June of end_year', function () {
    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'end_year' => 2026,
            'end_date' => '2026-05-31', // Before June
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'end_year' => 2026,
            'end_date' => '2026-07-01', // After June
        ])
        ->call('create')
        ->assertHasFormErrors(['end_date']);

    Livewire::test(CreateSchoolYear::class)
        ->fillForm([
            'start_year' => 2025,
            'end_year' => 2026,
            'end_date' => '2026-06-15', // Correct
        ])
        ->call('create')
        ->assertHasNoFormErrors(['end_date']);
});

test('can save a record', function () {
    $record = SchoolYear::factory()->create([
        'start_year' => 2024,
        'end_year' => 2025,
        'start_date' => '2024-07-01',
        'end_date' => '2025-06-15',
    ]);

    Livewire::test(EditSchoolYear::class, ['record' => $record->getRouteKey()])
        ->fillForm([
            'start_date' => '2024-07-02',
            'end_date' => '2025-06-16',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($record->refresh())
        ->start_date->format('Y-m-d')->toBe('2024-07-02')
        ->end_date->format('Y-m-d')->toBe('2025-06-16');
});

test('can save a record without changes', function () {
    $record = SchoolYear::factory()->create();

    Livewire::test(EditSchoolYear::class, ['record' => $record->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();
});
