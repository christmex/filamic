<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Classrooms\Tables;

use Christmex\FilamentToggleTableGroupAction\Actions\ToggleTableGroupAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClassroomsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('school.name')
            ->recordUrl(null)
            ->paginationPageOptions(['all'])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy('grade')->orderBy('name'))
            ->groups([
                Group::make('school.name')
                    ->collapsible()
                    ->label('Sekolah'),
            ])
            ->collapsedGroupsByDefault()
            ->toolbarActions([
                ToggleTableGroupAction::make(),
            ])
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('grade')
                    ->sortable(),
                TextColumn::make('identifier')
                    ->sortable(),
                TextColumn::make('phase'),
                IconColumn::make('is_moving_class')
                    ->label('Moving Class')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('school_id')
                    ->label('School')
                    ->relationship('school', 'name'),

                SelectFilter::make('is_moving_class')
                    ->label('Moving Class')
                    ->options([
                        true => 'Yes',
                        false => 'No',
                    ]),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
