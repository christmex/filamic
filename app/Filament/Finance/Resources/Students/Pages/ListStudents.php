<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\Pages;

use App\Actions\GenerateBookFeeInvoice;
use App\Actions\GenerateMonthlyFeeInvoice;
use App\Enums\MonthEnum;
use App\Filament\Finance\Resources\Students\StudentResource;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Services\BookFeeService;
use App\Services\MonthlyFeeService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    public function getSubheading(): string
    {
        $activeYear = SchoolYear::getActive();

        if (blank($activeYear)) {
            return 'Tahun Ajaran/Semester belum aktif! Mohon setel di pengaturan.';
        }

        $currentMonth = MonthEnum::from(now()->month)->getLabel();

        return "Berdasarkan Tahun Ajaran Aktif: {$activeYear->name} — Bulan saat ini: {$currentMonth}";
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('tabler-plus')
                ->color('gray'),
            ActionGroup::make([
                self::createMonthlyInvoiceAction(),
                self::createBookFeeInvoiceAction(),
            ])
                ->label('Buat Tagihan')
                ->color('success')
                ->button()
                ->icon('tabler-invoice'),
        ];
    }

    public static function createMonthlyInvoiceAction(): Action
    {
        return Action::make('createMonthlyInvoice')
            ->label('Uang Sekolah')
            ->requiresConfirmation()
            ->modalIcon('tabler-invoice')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalAlignment(Alignment::Start)
            ->modalHeading('Buat Tagihan SPP')
            ->modalIconColor('success')
            ->modalDescription(function () {
                $schoolYear = SchoolYear::getActive();
                $schoolTerm = SchoolTerm::getActive();

                if (blank($schoolYear) || blank($schoolTerm)) {
                    return 'Tahun Ajaran/Semester belum aktif.';
                }

                return str("Tahun Ajaran: **{$schoolYear->name}**  \n" .
                    "Semester: **{$schoolTerm->name->getLabel()}**")
                    ->markdown()
                    ->toHtmlString();
            })
            ->steps([
                Step::make('Konfigurasi')
                    ->icon('tabler-settings')
                    ->description('Pilih bulan dan tanggal tagihan')
                    ->schema([
                        Group::make([
                            Select::make('month')
                                ->options(function () {
                                    $currentTerm = SchoolTerm::getActive();
                                    if (blank($currentTerm)) {
                                        return [];
                                    }

                                    $allowedMonths = $currentTerm->getAllowedMonths();

                                    return collect(MonthEnum::filterByMonths($allowedMonths))
                                        ->mapWithKeys(fn ($month) => [$month->value => $month->getLabel()])
                                        ->toArray();
                                })
                                ->required()
                                ->label('Bulan')
                                ->live()
                                ->selectablePlaceholder(false)
                                ->default(now()->addMonth()->month)
                                ->columnSpanFull(),
                            DatePicker::make('issued_at')
                                ->label('Tagihan Dibuka')
                                ->required()
                                ->default(now()->setDay(28)),
                            DatePicker::make('due_date')
                                ->label('Tagihan Berakhir')
                                ->required()
                                ->after('issued_at')
                                ->default(now()->addMonth()->setDay(20)),
                        ])->columns(2),
                    ]),

                Step::make('Pratinjau')
                    ->icon('tabler-list-check')
                    ->description('Periksa data sebelum membuat tagihan')
                    ->schema(function (Get $get): array {
                        /** @var Branch $branch */
                        $branch = filament()->getTenant();

                        $monthlyFeeService = app(MonthlyFeeService::class);

                        $studentsWithoutInvoice = $monthlyFeeService->getStudentsWithoutInvoice($branch, $get('month'))
                            ->values()
                            ->mapWithKeys(fn (Student $student, int $index) => [($index + 1) . '. ' . $student->name => 'Tagihan Baru'])
                            ->toArray();

                        $unpaidInvoices = $monthlyFeeService->getUnpaidInvoices($branch, $get('month'))
                            ->values()
                            ->mapWithKeys(fn (Invoice $invoice, int $index) => [($index + 1) . '. ' . $invoice->student_name => $invoice->status->getLabel()])
                            ->toArray();

                        return [
                            KeyValueEntry::make('invoiced')
                                ->label('Peserta Didik Yang Sudah Memiliki Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state($unpaidInvoices),

                            KeyValueEntry::make('new_invoices')
                                ->label('Daftar Tagihan Baru')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state($studentsWithoutInvoice),
                        ];
                    }),
            ])
            ->action(function (array $data) {
                try {
                    $generateMonthlyFeeInvoice = GenerateMonthlyFeeInvoice::run(
                        filament()->getTenant(),
                        $data
                    );

                    if ($generateMonthlyFeeInvoice === 0) {
                        Notification::make()
                            ->title('Tagihan tidak dibuat!')
                            ->body('Tidak ada siswa yang memenuhi syarat pembuatan tagihan.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Berhasil membuat tagihan!')
                        ->body("{$generateMonthlyFeeInvoice} tagihan baru dibuat.")
                        ->success()
                        ->send();

                } catch (\Illuminate\Database\QueryException $error) {
                    if ($error->getCode() === '23000' && str_contains(mb_strtolower($error->getMessage()), 'fingerprint')) {
                        Notification::make()
                            ->title('Invoice sudah dibuat sebelumnya!')
                            ->warning()
                            ->send();

                        return;
                    }

                    throw $error;
                } catch (Throwable $error) {
                    report($error);

                    Notification::make()
                        ->title('Gagal membuat tagihan!')
                        ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }
            });
    }

    public static function createBookFeeInvoiceAction(): Action
    {
        return Action::make('createBookFeeInvoice')
            ->label('Uang Buku')
            ->requiresConfirmation()
            ->modalIcon('tabler-invoice')
            ->slideOver()
            ->modalWidth(Width::FourExtraLarge)
            ->modalAlignment(Alignment::Start)
            ->modalHeading('Buat Atau Perpanjang Tagihan Uang Buku')
            ->modalIconColor('success')
            ->modalDescription('Buat atau perpanjang tagihan uang buku untuk tahun ajaran depan')
            ->steps([
                Step::make('Konfigurasi')
                    ->icon('tabler-settings')
                    ->description('Atur tanggal tagihan')
                    ->schema([
                        Group::make([
                            Callout::make('Informasi')
                                ->color('warning')
                                ->icon('tabler-info-circle')
                                ->columnSpanFull()
                                ->description('Siswa yang saat ini berada di kelas TK B, 6 SD, 3 SMP, dan 3 SMA tidak akan dibuatkan tagihan uang buku.'),
                            DatePicker::make('issued_at')
                                ->label('Tagihan Dibuka')
                                ->required()
                                ->default(now()->startOfMonth()),
                            DatePicker::make('due_date')
                                ->label('Tagihan Berakhir')
                                ->required()
                                ->after('issued_at')
                                ->default(now()->endOfMonth()),
                            TextInput::make('increase_book_cost')
                                ->label('Nominal Penambahan Uang Buku')
                                ->minValue(0)
                                ->numeric()
                                ->prefix('IDR')
                                ->currencyMask(thousandSeparator: '.', decimalSeparator: ',', precision: 2)
                                ->columnSpanFull()
                                ->helperText('Ini akan menambahkan nominal tagihan uang buku (Uang Buku Siswa Sekarang + Nominal Yang ditambahkan)'),
                        ])->columns(2),
                    ]),

                Step::make('Pratinjau')
                    ->icon('tabler-check')
                    ->description('Periksa data sebelum membuat tagihan')
                    ->schema(function (): array {

                        /** @var Branch $branch */
                        $branch = filament()->getTenant();

                        $bookFeeService = app(BookFeeService::class);

                        return [
                            KeyValueEntry::make('new_invoices')
                                ->label('Daftar Tagihan Baru')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state(
                                    $bookFeeService->getStudentsWithoutInvoice($branch)
                                        ->values()
                                        ->mapWithKeys(fn (Student $student, int $index) => [($index + 1) . '. ' . $student->name => 'Tagihan Baru'])
                                        ->toArray()
                                ),

                            KeyValueEntry::make('invoiced')
                                ->label('Daftar Pembaruan Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state(
                                    $bookFeeService->getUnpaidInvoices($branch)
                                        ->values()
                                        ->mapWithKeys(fn (Invoice $invoice, int $index) => [($index + 1) . '. ' . $invoice->student_name => 'Tagihan Diperbaharui'])
                                        ->toArray()
                                ),
                        ];
                    }),
            ])
            ->action(function (array $data) {
                try {
                    $generateBookFeeInvoice = GenerateBookFeeInvoice::run(
                        filament()->getTenant(),
                        $data
                    );

                    if (empty($generateBookFeeInvoice)) {
                        Notification::make()
                            ->title('Tagihan tidak dibuat!')
                            ->body('Tidak ada siswa yang memenuhi syarat pembuatan tagihan.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Berhasil membuat tagihan!')
                        ->body("{$generateBookFeeInvoice['updated']} tagihan diperbarui dan {$generateBookFeeInvoice['created']} tagihan baru dibuat.")
                        ->success()
                        ->send();

                } catch (\Illuminate\Database\QueryException $error) {
                    if ($error->getCode() === '23000' && str_contains(mb_strtolower($error->getMessage()), 'fingerprint')) {
                        Notification::make()
                            ->title('Invoice sudah dibuat sebelumnya!')
                            ->warning()
                            ->send();

                        return;
                    }

                    throw $error;
                } catch (Throwable $error) {
                    report($error);

                    Notification::make()
                        ->title('Gagal membuat tagihan!')
                        ->body('Terjadi Kesalahan Sistem. Silakan hubungi tim IT.')
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }
            });
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make()
                ->label('Semua')
                ->icon('tabler-user')
                ->badge(fn () => Student::count()),
            'active' => Tab::make()
                ->label('Aktif')
                ->modifyQueryUsing(fn (Builder | Student $query) => $query->active())
                ->icon('tabler-user-check')
                ->badge(fn () => Student::active()->count()),
            'inactive' => Tab::make()
                ->label('Tidak Aktif')
                ->modifyQueryUsing(fn (Builder | Student $query) => $query->inActive())
                ->icon('tabler-user-x')
                ->badge(fn () => Student::inActive()->count()),

        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return 'active';
    }
}
