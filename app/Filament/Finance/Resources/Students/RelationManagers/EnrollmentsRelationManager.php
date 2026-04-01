<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\RelationManagers;

use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * @method Student getOwnerRecord()
 */
class EnrollmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'enrollments';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('school_year_id')
                ->label('Tahun Ajaran')
                ->relationship('schoolYear', 'name')
                ->getOptionLabelFromRecordUsing(fn (SchoolYear $record) => "{$record->name}")
                ->default(fn () => SchoolYear::getActive()?->getKey())
                ->required()
                ->columnSpanFull()
                ->hint(fn () => ($active = SchoolYear::getActive()) ? "Tahun ajaran aktif: {$active->name}" : 'Tahun ajaran belum aktif!'),
            Select::make('school_id')
                ->label('Unit Sekolah')
                ->relationship('school', 'name')
                ->required()
                ->distinct()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->relationship('school', 'name', function ($query) {
                    $query->where('branch_id', Filament::getTenant()->getKey());
                })
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('student_id', $this->getOwnerRecord()->getKey())
                ),
            Select::make('classroom_id')
                ->label('Pilih Kelas')
                ->options(fn (Get $get) => Classroom::with('school')
                    ->where('school_id', $get('school_id'))
                    ->get()
                    ->groupBy('school.name')
                    ->map(fn ($classroom) => $classroom->pluck('name', 'id'))
                )
                ->preload()
                ->optionsLimit(20)
                ->searchable()
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Data Kelas')
            ->defaultSort('status')
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('school.name')
                    ->label('Unit Sekolah'),
                TextColumn::make('classroom.name')
                    ->label('Kelas'),
                TextColumn::make('classroom.grade')
                    ->label('Tingkat'),
                TextColumn::make('schoolYear.name')
                    ->label('Tahun Ajaran'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Data Kelas')
                    ->hidden(fn () => $this->getOwnerRecord()->isActive())
                    ->mutateDataUsing(function (array $data): array {
                        $data['branch_id'] = Filament::getTenant()->getKey();

                        return $data;
                    }),
            ]);
    }
}
