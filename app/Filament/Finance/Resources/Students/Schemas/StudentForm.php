<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\Schemas;

use App\Enums\GenderEnum;
use App\Filament\Finance\Resources\Students\RelationManagers\BookFeeInvoicesRelationManager;
use App\Filament\Finance\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Finance\Resources\Students\RelationManagers\MonthlyFeeInvoicesRelationManager;
use App\Models\Student;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Operation;

class StudentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->persistTab()
                    ->tabs([
                        Tab::make('Detail Siswa')
                            ->schema([
                                Section::make([
                                    TextInput::make('name')
                                        ->label('Nama Lengkap')
                                        ->required()
                                        ->placeholder('Contoh: John Doe'),
                                    ToggleButtons::make('gender')
                                        ->label('Jenis Kelamin')
                                        ->options(GenderEnum::class)
                                        ->required()
                                        ->inline(),
                                    TextInput::make('previous_education')
                                        ->label('Pendidikan Sebelumnya')
                                        ->placeholder('Contoh: SDS Kasih Sayang'),
                                    TextInput::make('joined_at_class')
                                        ->label('Masuk di Kelas')
                                        ->placeholder('Contoh: VII (Joshua 1)'),
                                    Textarea::make('notes')
                                        ->label('Catatan Tambahan')
                                        ->columnSpanFull(),
                                ])->columns(2),
                            ])
                            ->icon('tabler-list-details'),
                        Tab::make('Data Kelas')
                            ->visibleOn(Operation::Edit)
                            ->schema([
                                Livewire::make(EnrollmentsRelationManager::class, fn (Page $livewire, Student $record) => [
                                    'ownerRecord' => $record,
                                    'pageClass' => $livewire::class,
                                ])->columnSpanFull(),
                            ])
                            ->icon('tabler-door'),
                        Tab::make('Tagihan')
                            ->visibleOn(Operation::Edit)
                            ->schema([
                                Livewire::make(MonthlyFeeInvoicesRelationManager::class, fn (Page $livewire, Student $record) => [
                                    'ownerRecord' => $record,
                                    'pageClass' => $livewire::class,
                                ])->columnSpanFull(),
                                Livewire::make(BookFeeInvoicesRelationManager::class, fn (Page $livewire, Student $record) => [
                                    'ownerRecord' => $record,
                                    'pageClass' => $livewire::class,
                                ])->columnSpanFull(),
                            ])
                            ->icon('tabler-invoice'),
                    ])
                    ->contained(false),
            ]);
    }
}
