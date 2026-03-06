<?php

namespace App\Filament\SupplyHub\Resources\Orders\Schemas;

use App\Models\SchoolYear;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('school_year_id')
                    ->relationship('schoolYear', 'name')
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn (SchoolYear $record) => "{$record->name}"),
                DatePicker::make('ordered_at')
                    ->required(),
                Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('coordinator')
                    ->required(),
                TextInput::make('person_in_charge')
                    ->required(),
                RichEditor::make('notes')
                    ->label('Catatan')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
