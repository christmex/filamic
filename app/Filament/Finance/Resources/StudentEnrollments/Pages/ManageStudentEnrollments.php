<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\StudentEnrollments\Pages;

use App\Filament\Finance\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Finance\Resources\StudentEnrollments\Tables\GraduateTable;
use App\Models\StudentEnrollment;
use Filament\Actions\Action;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ManageStudentEnrollments extends ManageRecords
{
    protected static string $resource = StudentEnrollmentResource::class;

    public function getHeaderActions(): array
    {
        return [
            Action::make('finalizeStudentGradePromotion')
                ->tooltip(fn () => StudentEnrollment::emptyClassroom()->count() ? 'Masih ada siswa yang belum memiliki Kelas Tujuan' : null)
                ->visible(fn () => StudentEnrollment::draft()->count())
                ->disabled(fn () => StudentEnrollment::emptyClassroom()->count())
                ->label('Finalisasi Data Kenaikan Kelas')
                ->requiresConfirmation()
                ->modalHeading('Apakah anda yakin?')
                ->modalDescription('Tindakan ini akan memfinalisasi data kenaikan kelas dan tidak dapat dikembalikan, jadi pastikan data sudah benar')
                ->modalIcon('tabler-check')
                ->modalIconColor('success')
                ->icon('tabler-check')
                ->color('success')
                ->action(function () {
                    // check apakah ada classroom_id yang masih kosong
                }),
        ];
    }

    // use form component tab instead
    // public function getTabs(): array
    // {
    //     return [
    //         Tab::make()
    //             ->label('Naik Kelas')
    //             ->modifyQueryUsing(fn (Builder $query) => $query),
    //         Tab::make()
    //             ->label('Pindah Cabang')
    //             ->modifyQueryUsing(fn (Builder $query) => $query),
    //     ];
    // }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Callout::make('Memulai')
                    ->icon('tabler-bulb')
                    // ->info()
                    ->color('primary')
                    ->hidden(fn () => StudentEnrollment::draft()->count())
                    ->description('Silahkan buat draft kenaikan kelas pada tabel dibawah'),
                Callout::make('Perlu Tindakan')
                    ->visible(fn () => StudentEnrollment::draft()->emptyClassroom()->count())
                    ->warning()
                    ->icon('tabler-alert-triangle')
                    ->description(
                        str('Selesaikan secara manual untuk siswa yang masih belum memiliki Kelas Tujuan, jika kelas tujuan tidak ditemukan, silahkan hubungi admin untuk dibuatkan kelas tujuan')
                            // ->append('**' . StudentEnrollment::draft()->emptyClassroom()->count() . ' siswa**')
                            // ->inlineMarkdown()
                            ->toHtmlString()
                    ),
                Callout::make('Informasi')
                    ->info()
                    ->description('Kelas Tujuan untuk peserta didik kelas 3 SD sengaja dikosongkan, admin harus input manual berdasarkan data yang akan diberikan, silahkan minta ke admin SD masing" cabang'),

                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                Section::make()
                    ->heading('Generate Otomatis')
                    ->description('Tabel dibawah ini untuk semua siswa yang naik kelas (SD, SMP, SMA) selain yang akan lulus')
                    ->icon('tabler-repeat')
                    ->contained(false)
                    ->iconColor('info')
                    ->collapsed(true)
                    ->persistCollapsed()
                    ->compact()
                    ->schema([
                        EmbeddedTable::make(),
                    ]),
                // Section::make()
                //     ->heading('Manual')
                //     ->description('Tabel dibawah ini untuk siswa kelas 3 SD yang naik kelas 4 SD')
                //     ->icon('tabler-hand-click')
                //     ->contained(false)
                //     ->iconColor('warning')
                //     ->collapsed(),
                Section::make()
                    ->heading('Akan Lulus')
                    ->description('Daftar peserta didik yang akan lulus (TK, SD, SMP) dan bisa lanjut ke jenjang selanjutnya')
                    ->icon('tabler-school')
                    ->contained(false)
                    ->iconColor('success')
                    ->collapsed(true)
                    ->persistCollapsed()
                    ->compact()
                    ->schema([
                        Livewire::make(GraduateTable::class)->columnSpanFull(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
