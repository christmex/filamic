<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Actions\FinalizeSchoolYearActivation;
use App\Actions\ProcessSchoolYearActivationChunk;
use App\Models\SchoolYear;
use App\Models\StudentEnrollment;
use Filament\Pages\Page;
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

    public function startProcessing(): void
    {
        if ($this->missingClassroomCount > 0) {
            return;
        }

        $this->state = 'processing';
        $this->dispatch('process-next-chunk');
    }

    public function processChunk(): void
    {
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
        } catch (Throwable $error) {
            report($error);
            $this->state = 'error';
            $this->errorMessage = $error->getMessage();
        }
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
