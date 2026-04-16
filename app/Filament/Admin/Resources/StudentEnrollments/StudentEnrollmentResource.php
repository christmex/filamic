<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StudentEnrollments;

use App\Enums\StudentEnrollmentStatusEnum;
use App\Filament\Admin\Resources\StudentEnrollments\Pages\ManageStudentEnrollments;
use App\Filament\Admin\Resources\Students\StudentResource;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudentEnrollmentResource extends Resource
{
    protected static ?string $model = StudentEnrollment::class;

    protected static string | BackedEnum | null $navigationIcon = 'tabler-chevrons-up';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Kenaikan Kelas';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([5, 10, 20])
            ->paginationMode(PaginationMode::Simple)
            ->query(
                StudentEnrollment::query()
                    ->with(['student.classroom', 'classroom', 'schoolYear'])
                    ->orderBy('classroom_id')
            )
            ->columns([
                TextColumn::make('student.name')
                    ->tooltip('Klik untuk lihat detail peserta didik')
                    ->label('Nama Peserta Didik')
                    ->description(fn (StudentEnrollment $record) => $record->student->formattedNisn) // @phpstan-ignore-line -- formattedNisn is a virtual Attribute accessor not visible to static analysis via the docblock
                    ->url(fn (StudentEnrollment $record) => StudentResource::getUrl('edit', ['record' => $record->student]))
                    ->searchable(),
                TextColumn::make('student.classroom.name')
                    ->label('Kelas Aktif'),
                TextColumn::make('classroom.name')
                    ->label('Kelas Tujuan')
                    ->placeholder('Belum ditentukan'),
                TextColumn::make('schoolYear.name')
                    ->label('Tahun Ajaran Baru'),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StudentEnrollmentStatusEnum::class),
                SelectFilter::make('school_year_id')
                    ->label('Tahun Ajaran')
                    ->options(SchoolYear::get()->pluck('name', 'id')),
                SelectFilter::make('student.classroom_id')
                    ->label('Kelas Aktif')
                    ->relationship('student.classroom', 'name', fn ($query, $livewire) => $query->whereRelation('school.branch', 'id', $livewire->activeTab)
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('classroom_id')
                    ->label('Kelas Tujuan')
                    ->options(fn ($livewire) => Classroom::query()
                        ->whereRelation('school.branch', 'id', $livewire->activeTab)
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
            ], FiltersLayout::AboveContent)
            ->filtersApplyAction(fn (Action $action) => $action->outlined())
            ->filtersFormColumns(4);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStudentEnrollments::route('/'),
        ];
    }
}
