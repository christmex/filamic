<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection;

class MonthlyFeeService
{
    /**
     * @return Collection<int, Student>
     */
    public function getStudentsWithoutInvoice(Branch $branch, int $month): Collection
    {
        return $branch
            ->students()
            ->active()
            ->notInFinalYears()
            ->whereDoesntHave('invoices', function ($query) use ($month): void {
                $query->monthlyFeeForThisSchoolYear(month: $month);
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
    public function getUnpaidInvoices(Branch $branch, int $month): Collection
    {
        return $branch
            ->invoices()
            ->unpaid()
            ->monthlyFeeForThisSchoolYear(month: $month)
            ->with('student')
            ->get();
    }
}
