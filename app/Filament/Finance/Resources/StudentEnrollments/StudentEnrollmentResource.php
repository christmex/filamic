<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\StudentEnrollments;

use App\Actions\GenerateGradePromotionDraft;
use App\Filament\Finance\Resources\StudentEnrollments\Pages\ManageStudentEnrollments;
use App\Filament\Finance\Resources\Students\StudentResource;
use App\Models\Classroom;
use App\Models\StudentEnrollment;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class StudentEnrollmentResource extends Resource
{
    protected static ?string $model = StudentEnrollment::class;

    protected static string | BackedEnum | null $navigationIcon = 'tabler-chevrons-up';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'Kenaikan Kelas';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->paginationPageOptions([5, 10, 20])
            ->paginationMode(PaginationMode::Simple)
            ->query(
                StudentEnrollment::draft()
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
                SelectFilter::make('student.classroom_id')
                    ->label('Kelas Aktif')
                    ->relationship('student.classroom', 'name', fn ($query) => $query
                        ->whereRelation('school.branch', 'id', filament()->getTenant()->getKey())
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('classroom_id')
                    ->label('Kelas Tujuan')
                    ->options(fn () => Classroom::query()
                        ->whereRelation('school.branch', 'id', filament()->getTenant()->getKey())
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
            ], FiltersLayout::AboveContent)
            ->filtersApplyAction(fn (Action $action) => $action->outlined())
            ->filtersFormColumns(2)
            ->recordActions([
                EditAction::make()
                    ->label(fn (StudentEnrollment $record) => $record->classroom_id ? 'Ganti Kelas' : 'Tentukan Kelas')
                    ->icon('tabler-edit')
                    ->modalHeading(fn (StudentEnrollment $record) => $record->classroom_id ? "Ganti Kelas Tujuan {$record->student->name}" : "Tentukan Kelas Tujuan {$record->student->name}")
                    ->schema([
                        Select::make('classroom_id')
                            ->label('Nama Kelas')
                            ->options(fn (StudentEnrollment $record) => Classroom::query()
                                ->where('school_id', $record->student->school_id)
                                ->where('grade', $record->student->classroom->getRawOriginal('grade') + 1)
                                ->pluck('name', 'id'))
                            ->required(),
                    ]),
                DeleteAction::make()
                    ->label('Hapus Data Kenaikan Kelas'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Data Kenaikan Kelas'),
                    Action::make('bulkMovingClassroom')
                        ->label('Pindah Cabang Massal')
                        ->icon('tabler-sparkles-2')
                        ->schema([
                            Select::make('classroom_id')
                                ->label('Nama Kelas')
                                ->options(fn () => Classroom::query()
                                    ->whereRelation('school.branch', 'id', filament()->getTenant()->getKey())
                                    ->pluck('name', 'id'))
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (array $data) {}),
                ]),
                // TODO: IMPORT EXCEL/CSV
                Action::make('import')
                    ->icon('tabler-file-import')
                    ->label('Import Excel/CSV'),
                Action::make('generateGradePromotionDraft')
                    ->label('Buat Draft Kenaikan Kelas')
                    ->icon('tabler-plus')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Apakah anda yakin?')
                    ->modalDescription('Tindakan ini akan membuat data draft kenaikan kelas')
                    ->modalIcon('tabler-plus')
                    ->action(function () {
                        try {
                            $generateGradePromotionDraft = GenerateGradePromotionDraft::run();

                            Notification::make()
                                ->title('Berhasil membuat draft kenaikan kelas!')
                                ->body("{$generateGradePromotionDraft} draft kenaikan kelas baru dibuat.")
                                ->success()
                                ->send();

                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal membuat draft kenaikan kelas!')
                                ->body($error->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStudentEnrollments::route('/'),
        ];
    }
}
