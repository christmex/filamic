<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Classrooms\Schemas;

use App\Enums\GradeEnum;
use App\Models\Classroom;
use App\Models\School;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                            ->afterStateHydrated(function (Set $set, $state) {
                                $school = School::find($state);

                                $set('temp_level', $school?->level->value ?? null);
                            })
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
                            ->placeholder('Example: Matthew 1')
                            ->unique(
                                table: Classroom::class,
                                column: 'name',
                                modifyRuleUsing: fn (Unique $rule, callable $get) => $rule->where('school_id', $get('school_id')),
                                ignoreRecord: true
                            ),
                        TextInput::make('phase')
                            ->placeholder('Example: A | B | C | D'),
                        TextInput::make('identifier')
                            ->placeholder('Example: 1 | 2 | 3 | etc')
                            ->required()
                            ->helperText('Digunakan saat menyarankan kelas selanjutnya, contoh: Matthew 1 ke Mark 1'),
                        ToggleButtons::make('is_moving_class')
                            ->boolean()
                            ->inline()
                            ->label('Is Moving Class')
                            ->default(false),
                    ]),
            ]);
    }
}
