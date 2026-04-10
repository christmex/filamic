<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\RelationManagers;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;
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
                ->hint(fn () => ($active = SchoolYear::getActive()) ? "Tahun ajaran aktif: {$active->name}" : 'Tahun ajaran belum aktif!')
                ->hiddenOn(Operation::Edit),
            Select::make('school_id')
                ->label('Unit Sekolah')
                ->relationship('school', 'name')
                ->required()
                ->distinct()
                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                ->afterStateUpdated(fn (Set $set) => $set('classroom_id', null))
                ->relationship('school', 'name', function ($query) {
                    $query->where('branch_id', Filament::getTenant()->getKey());
                })
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('student_id', $this->getOwnerRecord()->getKey())
                )
                ->hiddenOn(Operation::Edit),
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
                ->required()
                ->columnSpan(fn ($operation) => $operation === Operation::Edit->value ? 'full' : 1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Data Kelas')
            ->defaultSort('status')
            ->columns([
                TextColumn::make('branch.name')
                    ->label('Cabang'),
                TextColumn::make('school.name')
                    ->label('Unit Sekolah'),
                TextColumn::make('classroom.name')
                    ->label('Kelas')
                    ->placeholder('Belum ditentukan'),
                TextColumn::make('classroom.grade')
                    ->label('Tingkat')
                    ->placeholder('-'),
                TextColumn::make('schoolYear.name')
                    ->label('Tahun Ajaran'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->recordActions([
                EditAction::make()
                    ->modalHeading('Pindah Kelas')
                    ->visible(fn (StudentEnrollment $record) => $record->status === StudentEnrollmentStatusEnum::ENROLLED)
                    ->label('Pindah Kelas')
                    ->icon('tabler-replace'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Data Kelas')
                    ->hidden(fn () => $this->getOwnerRecord()->isActive())
                    ->mutateDataUsing(function (array $data): array {
                        $data['branch_id'] = Filament::getTenant()->getKey();
                        $data['status'] = StudentEnrollmentStatusEnum::ENROLLED;

                        return $data;
                    }),
            ]);
    }
}
