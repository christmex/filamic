<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\FinalizeSchoolYearActivation;
use App\Actions\ProcessSchoolYearActivationChunk;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ActivateSchoolYear extends Page
{
    protected string $view = 'filament.admin.pages.activate-school-year';

    protected static bool $shouldRegisterNavigation = false;

    // Page state: idle | processing | completed | error
    public string $state = 'idle';

    public string $schoolYearName = '';

    public ?string $schoolYearId = null;

    public int $total = 0;

    public int $missingClassroomCount = 0;

    public int $processed = 0;

    public int $promoted = 0;

    public int $stayed = 0;

    public int $graduated = 0;

    public int $deactivated = 0;

    public ?string $errorMessage = null;

    public function mount(): void
    {
        $nextSchoolYear = SchoolYear::getNextSchoolYear();

        if (blank($nextSchoolYear)) {
            $this->state = 'error';
            $this->errorMessage = 'Tahun ajaran selanjutnya tidak ditemukan. Pastikan tahun ajaran berikutnya sudah dibuat.';

            return;
        }

        $this->schoolYearId = $nextSchoolYear->getKey();
        $this->schoolYearName = $nextSchoolYear->name;

        $drafts = StudentEnrollment::draft()->where('school_year_id', $this->schoolYearId);
        $this->total = (clone $drafts)->count();
        $this->missingClassroomCount = (clone $drafts)->whereNull('classroom_id')->count();
    }

    /** Filament action — shows confirmation modal before calling startProcessing(). */
    public function startActivationAction(): Action
    {
        return Action::make('startActivation')
            ->label('Mulai Aktivasi')
            ->color('success')
            ->size('lg')
            ->requiresConfirmation()
            ->modalHeading(fn () => "Aktivasi Tahun Ajaran {$this->schoolYearName}")
            ->modalDescription(fn () => "Proses ini akan mengaktifkan {$this->total} siswa ke tahun ajaran {$this->schoolYearName}. Tindakan ini tidak dapat dibatalkan.")
            ->modalSubmitActionLabel('Ya, Mulai Sekarang')
            ->disabled(fn () => $this->missingClassroomCount > 0 || $this->total === 0)
            ->action(fn () => $this->startProcessing());
    }

    /** Filament action — resets page to idle state so the user can retry. */
    public function resetStateAction(): Action
    {
        return Action::make('resetState')
            ->label('Coba Lagi')
            ->color('gray')
            ->action(fn () => $this->resetState());
    }

    public function startProcessing(): void
    {
        if ($this->missingClassroomCount > 0) {
            return;
        }

        $lock = Cache::lock('school-year-activation-' . $this->schoolYearId, 7200);

        if (! $lock->get()) {
            Notification::make()
                ->warning()
                ->title('Proses Sedang Berjalan')
                ->body('Aktivasi tahun ajaran ini sedang diproses oleh sesi lain. Silakan tunggu hingga selesai.')
                ->send();

            return;
        }

        $this->state = 'processing';
        $this->dispatch('process-next-chunk');
    }

    public function processChunk(): void
    {
        if (blank($this->schoolYearId)) {
            $this->state = 'error';
            $this->errorMessage = 'ID tahun ajaran tidak ditemukan.';

            return;
        }

        try {
            $result = ProcessSchoolYearActivationChunk::run($this->schoolYearId);

            $this->processed += $result['processed'];
            $this->promoted += $result['promoted'];
            $this->stayed += $result['stayed'];
            $this->graduated += $result['graduated'];

            if ($result['remaining'] > 0) {
                $this->dispatch('process-next-chunk');
            } else {
                $this->finalize();
            }
        } catch (Throwable $error) {
            Cache::lock('school-year-activation-' . $this->schoolYearId)->forceRelease();
            report($error);
            $this->state = 'error';
            $this->errorMessage = $error->getMessage();
        }
    }

    public function finalize(): void
    {
        try {
            $result = FinalizeSchoolYearActivation::run($this->schoolYearId);
            $this->deactivated = $result['deactivated'];
            $this->state = 'completed';

            SchoolYear::find($this->schoolYearId)?->updateQuietly([
                'activated_at' => now(),
                'activated_by_id' => auth()->id(),
                'activation_summary' => [
                    'promoted' => $this->promoted,
                    'stayed' => $this->stayed,
                    'graduated' => $this->graduated,
                    'deactivated' => $this->deactivated,
                ],
            ]);

            $otherAdmins = User::where('id', '!=', auth()->id())->get();

            Notification::make()
                ->title('Tahun Ajaran ' . $this->schoolYearName . ' Diaktifkan')
                ->body(auth()->user()->name . ' mengaktifkan tahun ajaran baru. ' . number_format($this->promoted) . ' naik kelas, ' . number_format($this->stayed) . ' tinggal kelas, ' . number_format($this->graduated) . ' lulus.')
                ->success()
                ->sendToDatabase($otherAdmins);

            Notification::make()
                ->title('Aktivasi Berhasil')
                ->body("Tahun ajaran {$this->schoolYearName} berhasil diaktifkan.")
                ->success()
                ->send();
        } catch (Throwable $error) {
            report($error);
            $this->state = 'error';
            $this->errorMessage = $error->getMessage();
        } finally {
            Cache::lock('school-year-activation-' . $this->schoolYearId)->forceRelease();
        }
    }

    public function resetState(): void
    {
        $this->state = 'idle';
        $this->errorMessage = null;
        $this->processed = 0;
        $this->promoted = 0;
        $this->stayed = 0;
        $this->graduated = 0;
        $this->deactivated = 0;

        $nextSchoolYear = SchoolYear::getNextSchoolYear();

        if (blank($nextSchoolYear)) {
            $this->state = 'error';
            $this->errorMessage = 'Tahun ajaran selanjutnya tidak ditemukan. Pastikan tahun ajaran berikutnya sudah dibuat.';

            return;
        }

        $this->schoolYearId = $nextSchoolYear->getKey();
        $this->schoolYearName = $nextSchoolYear->name;

        $drafts = StudentEnrollment::draft()->where('school_year_id', $this->schoolYearId);
        $this->total = (clone $drafts)->count();
        $this->missingClassroomCount = (clone $drafts)->whereNull('classroom_id')->count();
    }

    public function getProgress(): int
    {
        if ($this->total === 0) {
            return 0;
        }

        return (int) round(($this->processed / $this->total) * 100);
    }

    public function getTitle(): string
    {
        return "Aktivasi Tahun Ajaran {$this->schoolYearName}";
    }
}
