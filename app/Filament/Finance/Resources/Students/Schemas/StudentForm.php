<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\Schemas;

use App\Enums\GenderEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Filament\Finance\Resources\Students\RelationManagers\BookFeeInvoicesRelationManager;
use App\Filament\Finance\Resources\Students\RelationManagers\EnrollmentsRelationManager;
use App\Filament\Finance\Resources\Students\RelationManagers\MonthlyFeeInvoicesRelationManager;
use App\Models\Classroom;
use App\Models\SchoolYear;
use App\Models\Student;
use Closure;
use Filament\Facades\Filament;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
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
                                    Repeater::make('enrollments')
                                        ->visibleOn(Operation::Create)
                                        ->label('Data Kelas')
                                        ->relationship('enrollments')
                                        ->defaultItems(0)
                                        ->maxItems(1)
                                        ->columnSpanFull()
                                        ->columns(3)
                                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                            $data['branch_id'] = Filament::getTenant()->getKey();
                                            $data['status'] = StudentEnrollmentStatusEnum::ENROLLED;

                                            return $data;
                                        })
                                        ->schema([
                                            Select::make('school_year_id')
                                                ->label('Tahun Ajaran')
                                                ->relationship('schoolYear', 'name')
                                                ->getOptionLabelFromRecordUsing(fn (SchoolYear $record) => "{$record->name}")
                                                ->default(fn () => SchoolYear::getActive()?->getKey())
                                                ->required()
                                                ->hint(fn () => ($active = SchoolYear::getActive()) ? "Tahun ajaran aktif: {$active->name}" : 'Tahun ajaran belum aktif!'),
                                            Select::make('school_id')
                                                ->label('Unit Sekolah')
                                                ->relationship('school', 'name')
                                                ->required()
                                                ->distinct()
                                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                                ->relationship('school', 'name', function ($query) {
                                                    $query->where('branch_id', Filament::getTenant()->getKey());
                                                }),
                                            Select::make('classroom_id')
                                                ->label('Pilih Kelas')
                                                ->options(fn (Get $get) => Classroom::with('school')
                                                    ->where('school_id', $get('school_id'))
                                                    ->get()
                                                    ->groupBy('school.name')
                                                    ->map(fn ($classroom) => $classroom->pluck('name', 'id'))
                                                )
                                                ->rules([
                                                    fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                                        if (blank($value) || blank($get('school_id'))) {
                                                            return;
                                                        }

                                                        if (! Classroom::where('id', $value)->where('school_id', $get('school_id'))->exists()) {
                                                            $fail('Kelas yang dipilih tidak sesuai dengan Unit Sekolah yang dipilih.');
                                                        }
                                                    },
                                                ])
                                                ->preload()
                                                ->optionsLimit(20)
                                                ->searchable()
                                                ->required(),
                                        ]),
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
