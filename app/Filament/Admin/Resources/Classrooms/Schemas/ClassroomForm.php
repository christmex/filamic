<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Classrooms\Schemas;

use App\Enums\GradeEnum;
use App\Models\School;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ClassroomForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        Select::make('school_id')
                            ->relationship('school', 'name')
                            ->live()
                            ->afterStateUpdated(function (Set $set, $state) {
                                $school = School::find($state);

                                $set('temp_level', $school?->level->value ?? null);

                                $set('grade', null);
                            })
                            ->required(),
                        Hidden::make('temp_level'),
                        Select::make('grade')
                            ->options(function (Get $get) {
                                $level = $get('temp_level');

                                if (blank($level)) {
                                    return [];
                                }

                                return collect(GradeEnum::forLevel((int) $level))
                                    ->mapWithKeys(fn ($grade) => [
                                        $grade->value => $grade->getLabel(),
                                    ]);
                            })
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->placeholder('Example: Matthew 1'),
                        TextInput::make('phase')
                            ->placeholder('Example: A|B|C|D'),
                        Checkbox::make('is_moving_class')
                            ->label('Is Moving Class'),
                    ]),
            ]);
    }
}
