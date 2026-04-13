<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SchoolYears\Tables;

use App\Models\SchoolYear;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                IconColumn::make('is_active'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (SchoolYear $record) => $record->is_active === true),
            ])
            ->defaultSort('start_year', 'desc');
    }
}
