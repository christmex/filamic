<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SchoolYears\Tables;

use App\Filament\Admin\Pages\ActivateSchoolYear;
use App\Models\SchoolYear;
use Filament\Actions\Action;
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
                    ->visible(fn (SchoolYear $record) => $record->isActive()),
                Action::make('activate')
                    ->label('Aktifkan Tahun Ajaran Selanjutnya')
                    ->icon('tabler-check')
                    ->color('success')
                    ->visible(fn (SchoolYear $record) => $record->isActive())
                    ->url(fn () => ActivateSchoolYear::getUrl()),
            ])
            ->defaultSort('start_year', 'desc');
    }
}
