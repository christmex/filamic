<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SchoolYears\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class SchoolYearForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('start_year')
                            ->required()
                            ->numeric()
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->disabledOn(Operation::Edit)
                            ->dehydrated(fn ($operation) => $operation === Operation::Create->value)
                            ->afterStateUpdated(function (Set $set, $state, string $operation) {
                                if ($operation === Operation::Edit->value || blank($state)) {
                                    return;
                                }

                                $startYear = (int) $state;
                                $endYear = $startYear + 1;

                                $set('end_year', $endYear);
                                $set('start_date', "{$startYear}-07-01");
                                $set('end_date', "{$endYear}-06-30");
                            }),
                        TextInput::make('end_year')
                            ->disabled()
                            ->hint('Auto generate based on the start year'),
                        DatePicker::make('start_date')
                            ->label('Start Date')
                            ->minDate(fn ($get) => $get('start_year')
                                ? now()->year((int) $get('start_year'))->month(7)->startOfMonth()
                                : null
                            )
                            ->maxDate(fn ($get) => $get('start_year')
                                ? now()->year((int) $get('start_year'))->month(7)->endOfMonth()
                                : null
                            ),
                        DatePicker::make('end_date')
                            ->label('End Date')
                            ->after('start_date')
                            ->minDate(fn ($get) => $get('start_year')
                                ? now()->year((int) $get('start_year') + 1)->month(6)->startOfMonth()
                                : null
                            )
                            ->maxDate(fn ($get) => $get('start_year')
                                ? now()->year((int) $get('start_year') + 1)->month(6)->endOfMonth()
                                : null
                            ),
                        Checkbox::make('is_active')
                            ->label('Active')
                            ->helperText('Only one school year should be active at a time')
                            ->hiddenOn(Operation::Edit),
                    ]),
            ]);
    }
}
