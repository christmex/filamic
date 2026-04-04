<?php

declare(strict_types=1);

namespace App\Filament\Finance\Resources\Students\Pages;

use App\Actions\GenerateBookFeeInvoice;
use App\Actions\GenerateMonthlyFeeInvoice;
use App\Enums\GradeEnum;
use App\Enums\MonthEnum;
use App\Filament\Finance\Resources\Students\StudentResource;
use App\Models\Invoice;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
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
use Illuminate\Support\Collection;
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
                        $draft = self::buildDraftPreview((int) $get('month'));

                        return [
                            Callout::make('Ringkasan')
                                ->description(
                                    str(
                                        "Total Siswa Aktif: **{$draft['total']}** " .
                                        "= Siap Dibuat: **{$draft['ready']}** " .
                                        "+ Sudah Punya Tagihan: **{$draft['invoiced']}** " .
                                        "+ Belum Siap: **{$draft['not_eligible_count']}**"
                                    )->inlineMarkdown()->toHtmlString()
                                )
                                ->info()
                                ->columnSpanFull(),

                            KeyValueEntry::make('not_eligible')
                                ->label('Peserta Didik Yang Belum Siap Dibuatkan Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Alasan')
                                ->columnSpanFull()
                                ->state(
                                    $draft['not_eligible_list']
                                        ->values()
                                        ->mapWithKeys(fn (array $item, int $index) => [($index + 1) . '. ' . $item['name'] => $item['reason']])
                                        ->toArray()
                                ),

                            KeyValueEntry::make('invoiced')
                                ->label('Peserta Didik Yang Sudah Memiliki Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state(
                                    $draft['invoiced_list']
                                        ->values()
                                        ->mapWithKeys(fn (array $item, int $index) => [($index + 1) . '. ' . $item['name'] => $item['status']])
                                        ->toArray()
                                ),
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
            ->modalHeading('Buat Tagihan Uang Buku')
            ->modalIconColor('success')
            ->modalDescription('Buat tagihan uang buku untuk tahun ajaran depan')
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
                    ->icon('tabler-list-check')
                    ->description('Periksa data sebelum membuat tagihan')
                    ->schema(function (): array {
                        $draft = self::buildBookFeeDraftPreview();

                        return [
                            Callout::make('Ringkasan')
                                ->description(
                                    str(
                                        "Total Siswa Aktif: **{$draft['total']}** " .
                                        "= Siap Dibuat: **{$draft['ready']}** " .
                                        "+ Sudah Punya Tagihan: **{$draft['invoiced']}** " .
                                        "+ Belum Siap: **{$draft['not_eligible_count']}**"
                                    )->inlineMarkdown()->toHtmlString()
                                )
                                ->info()
                                ->columnSpanFull(),

                            KeyValueEntry::make('not_eligible')
                                ->label('Peserta Didik Yang Belum Siap Dibuatkan Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Alasan')
                                ->columnSpanFull()
                                ->state(
                                    $draft['not_eligible_list']
                                        ->values()
                                        ->mapWithKeys(fn (array $item, int $index) => [($index + 1) . '. ' . $item['name'] => $item['reason']])
                                        ->toArray()
                                ),

                            KeyValueEntry::make('invoiced')
                                ->label('Peserta Didik Yang Sudah Memiliki Tagihan')
                                ->keyLabel('Nama')
                                ->valueLabel('Status')
                                ->columnSpanFull()
                                ->state(
                                    $draft['invoiced_list']
                                        ->values()
                                        ->mapWithKeys(fn (array $item, int $index) => [($index + 1) . '. ' . $item['name'] => $item['status']])
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

                    if ($generateBookFeeInvoice === 0) {
                        Notification::make()
                            ->title('Tagihan tidak dibuat!')
                            ->body('Tidak ada siswa yang memenuhi syarat pembuatan tagihan.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Berhasil membuat tagihan!')
                        ->body("{$generateBookFeeInvoice} tagihan baru dibuat.")
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

    /**
     * @return array{total: int, ready: int, invoiced: int, not_eligible_count: int, not_eligible_list: Collection, invoiced_list: Collection}
     */
    private static function buildDraftPreview(int $month): array
    {
        $branchId = filament()->getTenant()->getKey();

        $activeStudents = Student::query()
            ->active()
            ->get();

        $totalActive = $activeStudents->count();

        $invoicedInvoices = Invoice::query()
            ->monthlyFeeForThisSchoolYear(month: $month)
            ->with('student')
            ->get();

        $invoicedStudentIds = $invoicedInvoices->pluck('student_id');

        $invoicedList = $invoicedInvoices->map(fn (Invoice $invoice): array => [
            'name' => $invoice->student->name,
            'status' => $invoice->status->getLabel(),
        ]);

        $readyStudentIds = Student::active()
            ->where('branch_id', $branchId)
            ->whereHas('currentEnrollment')
            ->eligibleForMonthlyFee()
            ->whereDoesntHave('invoices', function ($query) use ($month): void {
                // @phpstan-ignore method.notFound (Larastan cannot resolve scopes inside whereDoesntHave closures)
                $query->monthlyFeeForThisSchoolYear(month: $month);
            })
            ->pluck('id');

        $readyCount = $readyStudentIds->count();

        $notEligibleStudents = $activeStudents->reject(
            fn (Student $student): bool => $invoicedStudentIds->contains($student->getKey())
                || $readyStudentIds->contains($student->getKey())
        );

        $notEligibleList = $notEligibleStudents->values()->map(fn (Student $student): array => [
            'name' => $student->name,
            'reason' => self::getIneligibilityReason($student),
        ]);

        return [
            'total' => $totalActive,
            'ready' => $readyCount,
            'invoiced' => $invoicedList->count(),
            'not_eligible_count' => $notEligibleList->count(),
            'not_eligible_list' => $notEligibleList,
            'invoiced_list' => $invoicedList,
        ];
    }

    private static function getIneligibilityReason(Student $student): string
    {
        if (blank($student->currentEnrollment)) {
            return 'Belum punya pendaftaran aktif';
        }

        if ($student->monthly_fee_virtual_account === null) {
            return 'VA SPP belum diisi';
        }

        if ($student->monthly_fee_amount <= 0) {
            return 'Biaya SPP belum diisi';
        }

        return 'Data tidak lengkap';
    }

    /**
     * @return array{total: int, ready: int, invoiced: int, not_eligible_count: int, not_eligible_list: Collection, invoiced_list: Collection}
     */
    private static function buildBookFeeDraftPreview(): array
    {
        $branchId = filament()->getTenant()->getKey();

        $activeStudents = Student::query()
            ->active()
            ->with(['currentEnrollment.classroom'])
            ->get();

        $totalActive = $activeStudents->count();

        $invoicedInvoices = Invoice::where('branch_id', $branchId)
            ->bookFeeForNextSchoolYear()
            ->with('student')
            ->get();

        $invoicedStudentIds = $invoicedInvoices->pluck('student_id');

        $invoicedList = $invoicedInvoices->map(fn (Invoice $invoice): array => [
            'name' => $invoice->student->name,
            'status' => $invoice->status->getLabel(),
        ]);

        $readyStudentIds = Student::query()
            ->active()
            ->eligibleForBookFee()
            ->notInFinalYears()
            ->whereDoesntHave('invoices', function ($query): void {
                // @phpstan-ignore method.notFound (Larastan cannot resolve scopes inside whereDoesntHave closures)
                $query->bookFeeForNextSchoolYear();
            })
            ->pluck('id');

        $readyCount = $readyStudentIds->count();

        $notEligibleStudents = $activeStudents->reject(
            fn (Student $student): bool => $invoicedStudentIds->contains($student->getKey())
                || $readyStudentIds->contains($student->getKey())
        );

        $notEligibleList = $notEligibleStudents->values()->map(fn (Student $student): array => [
            'name' => $student->name,
            'reason' => self::getBookFeeIneligibilityReason($student),
        ]);

        return [
            'total' => $totalActive,
            'ready' => $readyCount,
            'invoiced' => $invoicedList->count(),
            'not_eligible_count' => $notEligibleList->count(),
            'not_eligible_list' => $notEligibleList,
            'invoiced_list' => $invoicedList,
        ];
    }

    private static function getBookFeeIneligibilityReason(Student $student): string
    {
        if (blank($student->currentEnrollment)) {
            return 'Belum punya pendaftaran aktif';
        }

        if (in_array($student->currentEnrollment->classroom->grade, GradeEnum::finalYears(), true)) {
            return 'Siswa di tingkat akhir';
        }

        if ($student->book_fee_amount <= 0) {
            return 'Biaya Uang Buku belum diisi';
        }

        if ($student->book_fee_virtual_account === null) {
            return 'VA Uang Buku belum diisi';
        }

        return 'Data tidak lengkap';
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
                ->modifyQueryUsing(fn (Builder | Student $query) => $query->active()->hasNoProblems())
                ->icon('tabler-user-check')
                ->badge(fn () => Student::active()->hasNoProblems()->count()),
            // 'has_problems' => Tab::make()
            //     ->label('Data Siswa Aktif Bermasalah')
            //     ->modifyQueryUsing(fn (Builder | Student $query) => $query->active()->hasProblems())
            //     ->icon('tabler-user-x')
            //     ->badgeColor('danger')
            //     ->badge(fn () => Student::active()->hasProblems()->count()),
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
