<?php

declare(strict_types=1);

use App\Actions\GenerateMonthlyFeeInvoice;
use App\Enums\InvoiceTypeEnum;
use App\Enums\MonthEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentPaymentAccount;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->schoolYear = SchoolYear::factory()->active()->create();
    $this->branch = Branch::factory()->create();
    $this->classroom = Classroom::factory()->create([
        'school_id' => $this->branch->schools()->first()?->getKey()
            ?? App\Models\School::factory()->create(['branch_id' => $this->branch->getKey()])->getKey(),
    ]);

    $this->validData = [
        'month' => 3,
        'issued_at' => '2026-02-28',
        'due_date' => '2026-03-20',
    ];
});

function createEligibleStudent(object $testContext): Student
{
    $student = Student::factory()->active()->create([
        'branch_id' => $testContext->branch->getKey(),
        'school_id' => $testContext->classroom->school_id,
        'classroom_id' => $testContext->classroom->getKey(),
    ]);

    StudentEnrollment::factory()->active()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $testContext->classroom->getKey(),
        'school_year_id' => $testContext->schoolYear->getKey(),
    ]);

    StudentPaymentAccount::factory()->create([
        'student_id' => $student->getKey(),
        'school_id' => $testContext->classroom->school_id,
    ]);

    return $student;
}

// Validation

test('cannot generate if required data are blank', function () {
    expect(fn () => GenerateMonthlyFeeInvoice::run($this->branch, []))
        ->toThrow(ValidationException::class);
});

test('cannot generate if month is out of range', function () {
    expect(fn () => GenerateMonthlyFeeInvoice::run($this->branch, [
        'month' => 13,
        'issued_at' => '2026-02-28',
        'due_date' => '2026-03-20',
    ]))->toThrow(ValidationException::class);
});

test('cannot generate if due_date is before issued_at', function () {
    expect(fn () => GenerateMonthlyFeeInvoice::run($this->branch, [
        'month' => 3,
        'issued_at' => '2026-03-20',
        'due_date' => '2026-02-28',
    ]))->toThrow(ValidationException::class);
});

// Happy path

test('can generate invoice for eligible student', function () {
    $student = createEligibleStudent($this);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(1);

    $invoice = Invoice::where('student_id', $student->getKey())->first();

    expect($invoice)
        ->not->toBeNull()
        ->type->toBe(InvoiceTypeEnum::MONTHLY_FEE)
        ->month->toBe(MonthEnum::March)
        ->issued_at->toDateString()->toBe('2026-02-28')
        ->due_date->toDateString()->toBe('2026-03-20')
        ->student_name->toBe($student->name)
        ->branch_id->toBe($this->branch->getKey());
});

test('generates invoices for multiple eligible students', function () {
    createEligibleStudent($this);
    createEligibleStudent($this);
    createEligibleStudent($this);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(3)
        ->and(Invoice::count())->toBe(3);
});

test('invoice amount matches payment account monthly fee', function () {
    $student = createEligibleStudent($this);

    $paymentAccount = $student->currentPaymentAccount;

    GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    $invoice = Invoice::where('student_id', $student->getKey())->first();

    expect($invoice->amount)->toBe($paymentAccount->monthly_fee_amount)
        ->and($invoice->total_amount)->toBe($paymentAccount->monthly_fee_amount);
});

// Edge cases — students that should be skipped

test('returns zero when no eligible students exist', function () {
    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0)
        ->and(Invoice::count())->toBe(0);
});

test('skips inactive students', function () {
    $student = Student::factory()->inactive()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);

    StudentEnrollment::factory()->active()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
    ]);

    StudentPaymentAccount::factory()->create([
        'student_id' => $student->getKey(),
        'school_id' => $this->classroom->school_id,
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('skips students without current enrollment', function () {
    $student = Student::factory()->active()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);

    StudentPaymentAccount::factory()->create([
        'student_id' => $student->getKey(),
        'school_id' => $this->classroom->school_id,
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('skips students without payment account', function () {
    $student = Student::factory()->active()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);

    StudentEnrollment::factory()->active()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('skips students with ineligible payment account (no VA)', function () {
    $student = Student::factory()->active()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);

    StudentEnrollment::factory()->active()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
    ]);

    StudentPaymentAccount::factory()->withoutMonthlyFee()->create([
        'student_id' => $student->getKey(),
        'school_id' => $this->classroom->school_id,
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('skips students who already have invoice for the same month', function () {
    $student = createEligibleStudent($this);

    Invoice::factory()->monthlyFee()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
        'month' => 3,
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('generates invoice for student with existing invoice in different month', function () {
    $student = createEligibleStudent($this);

    Invoice::factory()->monthlyFee()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
        'month' => 2,
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(1);
});

test('skips students from a different branch', function () {
    $otherBranch = Branch::factory()->create();
    $otherSchool = App\Models\School::factory()->create(['branch_id' => $otherBranch->getKey()]);
    $otherClassroom = Classroom::factory()->create(['school_id' => $otherSchool->getKey()]);

    $student = Student::factory()->active()->create([
        'branch_id' => $otherBranch->getKey(),
        'school_id' => $otherSchool->getKey(),
        'classroom_id' => $otherClassroom->getKey(),
    ]);

    StudentEnrollment::factory()->active()->create([
        'student_id' => $student->getKey(),
        'classroom_id' => $otherClassroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
    ]);

    StudentPaymentAccount::factory()->create([
        'student_id' => $student->getKey(),
        'school_id' => $otherSchool->getKey(),
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(0);
});

test('only generates for eligible students in a mixed group', function () {
    $eligible1 = createEligibleStudent($this);
    $eligible2 = createEligibleStudent($this);

    // Inactive student
    Student::factory()->inactive()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);

    // Active but no enrollment
    $noEnrollment = Student::factory()->active()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);
    StudentPaymentAccount::factory()->create([
        'student_id' => $noEnrollment->getKey(),
        'school_id' => $this->classroom->school_id,
    ]);

    // Active with enrollment but no payment account
    $noPayment = Student::factory()->active()->create([
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->classroom->school_id,
        'classroom_id' => $this->classroom->getKey(),
    ]);
    StudentEnrollment::factory()->active()->create([
        'student_id' => $noPayment->getKey(),
        'classroom_id' => $this->classroom->getKey(),
        'school_year_id' => $this->schoolYear->getKey(),
    ]);

    $count = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($count)->toBe(2)
        ->and(Invoice::pluck('student_id')->sort()->values()->toArray())
        ->toBe(collect([$eligible1->getKey(), $eligible2->getKey()])->sort()->values()->toArray());
});

test('each generated invoice has a unique fingerprint', function () {
    createEligibleStudent($this);
    createEligibleStudent($this);

    GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    $fingerprints = Invoice::pluck('fingerprint');

    expect($fingerprints)->toHaveCount(2)
        ->and($fingerprints->unique())->toHaveCount(2);
});

test('each generated invoice has a unique reference number', function () {
    createEligibleStudent($this);
    createEligibleStudent($this);

    GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    $referenceNumbers = Invoice::pluck('reference_number');

    expect($referenceNumbers)->toHaveCount(2)
        ->and($referenceNumbers->unique())->toHaveCount(2);
});

test('running same month twice does not create duplicate invoices', function () {
    createEligibleStudent($this);

    $firstRun = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);
    $secondRun = GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    expect($firstRun)->toBe(1)
        ->and($secondRun)->toBe(0)
        ->and(Invoice::count())->toBe(1);
});

test('invoice stores denormalized names correctly', function () {
    $student = createEligibleStudent($this);
    $enrollment = $student->currentEnrollment;
    $classroom = $enrollment->classroom;
    $school = $student->school;

    GenerateMonthlyFeeInvoice::run($this->branch, $this->validData);

    $invoice = Invoice::where('student_id', $student->getKey())->first();

    expect($invoice)
        ->branch_name->toBe($this->branch->name)
        ->school_name->toBe($school->name)
        ->classroom_name->toBe($classroom->name)
        ->school_year_name->toBe($this->schoolYear->name)
        ->student_name->toBe($student->name);
});
