<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

final class BookFeeService
{
    /**
     * @return Collection<int, Student>
     */
    public function getStudentsWithoutInvoice(Branch $branch): Collection
    {
        return $branch
            ->students()
            ->active()
            ->notInFinalYears()
            ->whereDoesntHave('invoices', function ($query): void {
                $query->bookFeeForNextSchoolYear();
            })
            ->with([
                'school',
                'currentEnrollment.classroom',
                'currentEnrollment.schoolYear',
            ])
            ->get();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function getUnpaidInvoices(Branch $branch): Collection
    {
        return $branch
            ->invoices()
            ->unpaid()
            ->bookFeeForNextSchoolYear()
            ->with('student')
            ->get();
    }
}
