<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\StudentEnrollments\Pages;

use App\Actions\ActivateNextSchoolYear;
use App\Filament\Admin\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Finance\Resources\StudentEnrollments\Pages\ManageStudentEnrollments as ManageStudentEnrollmentsMaster;
use App\Models\Branch;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;
use Throwable;

class ManageStudentEnrollments extends ManageRecords
{
    protected static string $resource = StudentEnrollmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
            Action::make('finalizeGradePromotion')
                ->icon('tabler-chevrons-up')
                ->label('Mulai Migrasi ke Tahun Ajaran Selanjutnya')
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
        ];
    }

    public function getTabs(): array
    {
        $branches = Branch::pluck('name', 'id');
        $tabs = [];

        foreach ($branches as $key => $branch) {
            $tabs[$key] = Tab::make($branch)
                ->icon('tabler-building')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('branch_id', $key));
        }

        return $tabs;
    }

    // public function content(Schema $schema): Schema
    // {
    //     return $schema
    //         ->components([
    //             Livewire::make(ManageStudentEnrollmentsMaster::class)->columnSpanFull(),
    //         ]);
    // }
}
