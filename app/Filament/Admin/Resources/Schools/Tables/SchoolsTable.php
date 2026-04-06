<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Schools\Tables;

use App\Models\School;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SchoolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('branch.name')
            ->recordUrl(null)
            ->columns([
                TextColumn::make('name')
                    ->description(fn (School $school) => $school->formatted_npsn),
                TextColumn::make('level')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
