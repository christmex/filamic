<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SchoolTerms;

use App\Filament\Admin\Resources\SchoolTerms\Pages\ManageSchoolTerms;
use App\Models\SchoolTerm;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SchoolTermResource extends Resource
{
    protected static ?string $model = SchoolTerm::class;

    protected static UnitEnum | string | null $navigationGroup = 'School Infrastructure';

    protected static ?int $navigationSort = 4;

    protected static string | BackedEnum | null $navigationIcon = 'tabler-calendar';

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([
                TextColumn::make('name'),
                IconColumn::make('is_active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSchoolTerms::route('/'),
        ];
    }
}
