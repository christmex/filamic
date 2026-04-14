<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\StudentEnrollments\Tables;

use App\Actions\CreateNextLevelEnrollment;
use App\Actions\UpdateNextLevelEnrollmentClassroom;
use App\Enums\GradeEnum;
use App\Models\Classroom;
use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Component;
use Throwable;

class GraduateTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->paginationPageOptions([5, 10, 20])
            ->paginationMode(PaginationMode::Simple)
            ->query(fn (): Builder => Student::where('branch_id', filament()->getTenant()->getKey())->inFinalYears(exclude: [GradeEnum::GRADE_12]))
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Peserta Didik')
                    ->searchable(),
                TextColumn::make('classroom.name')
                    ->label('Kelas Aktif')
                    ->searchable(),
                TextColumn::make('nextEnrollment.classroom.name')
                    ->label('Kelas Tujuan')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('nextEnrollment.schoolYear.name')
                    ->label('Tahun Ajaran Baru')
                    ->searchable(),
                TextColumn::make('nextEnrollment.status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('classroom_id')
                    ->label('Kelas Aktif')
                    ->relationship('classroom', 'name', fn ($query) => $query
                        ->onlyInFinalYears(exclude: [GradeEnum::GRADE_12])
                        ->whereRelation('school.branch', 'id', filament()->getTenant()->getKey())
                        ->orderBy('grade')
                        ->orderBy('name')
                    )
                    ->searchable()
                    ->preload(),
                SelectFilter::make('nextEnrollment.classroom_id')
                    ->label('Kelas Tujuan')
                    ->relationship('nextEnrollment.classroom', 'name', fn ($query) => $query
                        ->onlyInFirstYears()
                        ->whereRelation('school.branch', 'id', filament()->getTenant()->getKey())
                        ->orderBy('grade')
                        ->orderBy('name')
                    )
                    ->searchable()
                    ->preload(),
            ], FiltersLayout::AboveContent)
            ->filtersApplyAction(fn (Action $action) => $action->outlined())
            ->filtersFormColumns(2)
            ->recordActions([
                Action::make('editNextEnrollmentClassroom')
                    ->visible(fn (Student $record) => $record->nextEnrollment !== null)
                    ->icon('tabler-edit')
                    ->label('Ganti Kelas')
                    ->modalHeading(fn (Student $record) => "Ganti Kelas Tujuan {$record->name}")
                    ->schema([
                        Select::make('classroom_id')
                            ->label('Nama Kelas')
                            ->default(fn (Student $record) => $record->nextEnrollment->classroom_id)
                            ->options(fn (Student $record) => Classroom::query()
                                ->onlyInFirstYears()
                                ->whereRelation('school', 'level', '>', $record->school->level)
                                ->whereRelation('school', 'branch_id', '=', $record->branch_id)
                                ->where('grade', $record->classroom->getRawOriginal('grade') + 1)
                                ->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, Student $record) {
                        try {
                            UpdateNextLevelEnrollmentClassroom::run(
                                $record,
                                Classroom::findOrFail($data['classroom_id']),
                            );

                            Notification::make()
                                ->title('Kelas tujuan berhasil diubah!')
                                ->success()
                                ->send();
                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal mengubah kelas tujuan!')
                                ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('addNextEnrollment')
                    ->visible(fn (Student $record) => $record->nextEnrollment === null)
                    ->label('Daftarkan Ke Jenjang Selanjutnya')
                    ->icon('tabler-plus')
                    ->modalHeading(fn (Student $record) => "Daftarkan {$record->name} Ke Jenjang Selanjutnya")
                    ->modalDescription('Tindakan ini akan mendaftarkan anak ke jenjang selanjutnya di cabang ' . filament()->getTenant()->name) /** @phpstan-ignore property.notFound */
                    ->schema([
                        Select::make('classroom_id')
                            ->label('Nama Kelas')
                            ->options(fn (Student $record) => Classroom::query()
                                ->onlyInFirstYears()
                                ->whereRelation('school', 'level', '>', $record->school->level)
                                ->whereRelation('school', 'branch_id', '=', $record->branch_id)
                                ->where('grade', $record->classroom->getRawOriginal('grade') + 1)
                                ->pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data, Student $record) {
                        try {
                            CreateNextLevelEnrollment::run(
                                $record,
                                Classroom::findOrFail($data['classroom_id']),
                            );

                            Notification::make()
                                ->title('Peserta didik berhasil didaftarkan!')
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException $error) {
                            Notification::make()
                                ->title('Gagal mendaftarkan!')
                                ->body($error->getMessage())
                                ->warning()
                                ->send();
                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal mendaftarkan!')
                                ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                ->danger()
                                ->send();
                        }
                    }),
                DeleteAction::make()
                    ->label('Hapus Pendaftaran')
                    ->visible(fn (Student $record) => $record->nextEnrollment !== null)
                    ->requiresConfirmation()
                    ->modalHeading(fn (Student $record) => "Hapus Pendaftaran {$record->name}")
                    ->modalDescription('Tindakan ini akan menghapus pendaftaran peserta didik dari jenjang selanjutnya.')
                    ->using(function (Student $record) {
                        try {
                            $record->nextEnrollment->delete();

                            Notification::make()
                                ->title('Peserta didik berhasil dihapus dari pendaftaran!')
                                ->success()
                                ->send();
                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal menghapus peserta didik dari pendaftaran!')
                                ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus Pendaftaran'),
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
            ]);
    }

    public function render(): string
    {
        return '{{ $this->table }}';
    }
}
