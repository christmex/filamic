<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\StudentEnrollments\Pages;

use App\Filament\Finance\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Filament\Finance\Resources\StudentEnrollments\Tables\GraduateTable;
use App\Models\StudentEnrollment;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;

class ManageStudentEnrollments extends ManageRecords
{
    protected static string $resource = StudentEnrollmentResource::class;

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
                    ->collapsed(false)
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
                    ->collapsed(false)
                    ->persistCollapsed()
                    ->compact()
                    ->schema([
                        Livewire::make(GraduateTable::class)->columnSpanFull(),
                    ]),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
