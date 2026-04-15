<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\SchoolYears\Tables;

use App\Actions\ActivateNextSchoolYear;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Throwable;

class SchoolYearsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name'),
                IconColumn::make('is_active'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (SchoolYear $record) => $record->isActive()),
                Action::make('activate')
                    ->label('Aktifkan Tahun Ajaran Selanjutnya')
                    ->icon('tabler-check')
                    ->color('success')
                    ->visible(fn (SchoolYear $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalDescription(function (): string {
                        $nextSchoolYear = SchoolYear::getNextSchoolYear();

                        if (blank($nextSchoolYear)) {
                            return 'Tahun ajaran selanjutnya tidak ditemukan.';
                        }

                        $draftCount = StudentEnrollment::draft()
                            ->where('school_year_id', $nextSchoolYear->getKey())
                            ->count();

                        return "Tahun ajaran {$nextSchoolYear->name} akan diaktifkan. Sebanyak {$draftCount} siswa akan diproses.";
                    })
                    ->action(function () {
                        try {
                            $processed = ActivateNextSchoolYear::run();

                            Notification::make()
                                ->title('Berhasil mengaktifkan tahun ajaran selanjutnya!')
                                ->body("{$processed} siswa berhasil diproses.")
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException | ModelNotFoundException $error) {
                            Notification::make()
                                ->title('Gagal Mengaktifkan Tahun Ajaran Selanjutnya')
                                ->body($error->getMessage())
                                ->warning()
                                ->send();
                        } catch (Throwable $error) {
                            report($error);

                            Notification::make()
                                ->title('Gagal Mengaktifkan Tahun Ajaran Selanjutnya')
                                ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            ->defaultSort('start_year', 'desc');
    }
}
