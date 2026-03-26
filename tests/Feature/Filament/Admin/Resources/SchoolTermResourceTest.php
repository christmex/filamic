<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\SchoolTerms\Pages\ManageSchoolTerms;
use App\Filament\Admin\Resources\SchoolTerms\SchoolTermResource;
use App\Models\SchoolTerm;
use Livewire\Livewire;

beforeEach(fn () => $this->loginAdmin());

test('manage page is accessible', function () {
    // Act & Assert
    $this->get(SchoolTermResource::getUrl())->assertOk();
});

test('manage page renders columns', function (string $column) {
    // Arrange
    SchoolTerm::factory()->odd()->create();

    // Act & Assert
    Livewire::test(ManageSchoolTerms::class)
        ->assertCanRenderTableColumn($column);
})->with([
    'name',
    'is_active',
]);

// it('can toggle is_active column exclusively', function () {
//     // Arrange
//     $odd = SchoolTerm::factory()->odd()->active()->create();
//     $even = SchoolTerm::factory()->even()->inactive()->create();

//     expect(SchoolTerm::query()->active()->count())->toBe(1)
//         ->and(SchoolTerm::query()->active()->first()->getKey())->toBe($odd->getKey());

//     // Act
//     Livewire::test(ManageSchoolTerms::class)
//         ->call('updateTableColumnState', 'is_active', $even->getKey(), true)
//         ->assertHasNoErrors();

//     // Assert
//     expect(SchoolTerm::query()->active()->count())->toBe(1)
//         ->and(SchoolTerm::query()->active()->first()->getKey())->toBe($even->getKey())
//         ->and($odd->refresh()->is_active)->toBeFalse();
// });
