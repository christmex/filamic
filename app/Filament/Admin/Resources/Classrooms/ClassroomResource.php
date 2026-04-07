<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Classrooms;

use App\Filament\Admin\Resources\Classrooms\Pages\CreateClassroom;
use App\Filament\Admin\Resources\Classrooms\Pages\EditClassroom;
use App\Filament\Admin\Resources\Classrooms\Pages\ListClassrooms;
use App\Filament\Admin\Resources\Classrooms\Schemas\ClassroomForm;
use App\Filament\Admin\Resources\Classrooms\Tables\ClassroomsTable;
use App\Models\Classroom;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ClassroomResource extends Resource
{
    protected static ?string $model = Classroom::class;

    protected static UnitEnum | string | null $navigationGroup = 'School Infrastructure';

    protected static ?int $navigationSort = 3;

    protected static string | BackedEnum | null $navigationIcon = 'tabler-door';

    public static function form(Schema $schema): Schema
    {
        return ClassroomForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClassroomsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClassrooms::route('/'),
            'create' => CreateClassroom::route('/create'),
            'edit' => EditClassroom::route('/{record}/edit'),
        ];
    }
}
