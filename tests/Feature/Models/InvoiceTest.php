<?php

declare(strict_types=1);

use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\MonthEnum;
use App\Enums\PaymentMethodEnum;
use App\Models\Invoice;
use App\Models\SchoolYear;
use App\Models\Student;
use Illuminate\Support\Carbon;

afterEach(fn () => Carbon::setTestNow());

// --- Attribute Casting ---

it('casts type to InvoiceTypeEnum', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['type' => InvoiceTypeEnum::MONTHLY_FEE]);

    // Assert
    expect($invoice->type)
        ->toBe(InvoiceTypeEnum::MONTHLY_FEE)
        ->toBeInstanceOf(InvoiceTypeEnum::class);
});

it('casts status to InvoiceStatusEnum', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['status' => InvoiceStatusEnum::UNPAID]);

    // Assert
    expect($invoice->status)
        ->toBe(InvoiceStatusEnum::UNPAID)
        ->toBeInstanceOf(InvoiceStatusEnum::class);
});

it('casts month to MonthEnum', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['month' => MonthEnum::January]);

    // Assert
    expect($invoice->month)
        ->toBe(MonthEnum::January)
        ->toBeInstanceOf(MonthEnum::class);
});

it('casts payment_method to PaymentMethodEnum', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->paid()->create([
        'payment_method' => PaymentMethodEnum::VA,
    ]);

    // Assert
    expect($invoice->payment_method)
        ->toBe(PaymentMethodEnum::VA)
        ->toBeInstanceOf(PaymentMethodEnum::class);
});

it('casts paid_at to datetime', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->paid()->create(['paid_at' => '2024-01-15 10:30:00']);

    // Assert
    expect($invoice->paid_at)->toBeInstanceOf(Carbon::class);
});

it('casts amount fine discount total_amount to integer', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create([
        'amount' => 100000,
        'fine' => 5000,
        'discount' => 10000,
        'total_amount' => 95000,
    ]);

    // Assert
    expect($invoice->amount)->toBeInt()->toBe(100000)
        ->and($invoice->fine)->toBeInt()->toBe(5000)
        ->and($invoice->discount)->toBeInt()->toBe(10000)
        ->and($invoice->total_amount)->toBeInt()->toBe(95000);
});

// --- Model Events (booted) ---

it('generates fingerprint on create', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->monthlyFee()->create();

    // Assert
    expect($invoice->fingerprint)
        ->not->toBeEmpty()
        ->toBeString();
});

it('generates reference number on create', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create();

    // Assert
    expect($invoice->reference_number)
        ->toStartWith('INV/')
        ->toBeString();
});

// --- Scopes ---

it('paid scope returns only paid invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->paid()->create();
    Invoice::factory(2)->for($student)->unpaid()->create();

    // Act
    $result = Invoice::paid()->get();

    // Assert
    expect($result)->toHaveCount(1);
    foreach ($result as $inv) {
        expect($inv->status)->toBe(InvoiceStatusEnum::PAID);
    }
});

it('unpaid scope returns only unpaid invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->paid()->create();
    Invoice::factory(2)->for($student)->unpaid()->create();

    // Act
    $result = Invoice::unpaid()->get();

    // Assert
    expect($result)->toHaveCount(2);
    foreach ($result as $inv) {
        expect($inv->status)->toBe(InvoiceStatusEnum::UNPAID);
    }
});

it('monthlyFee scope returns only monthly fee invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->create();
    Invoice::factory(2)->for($student)->bookFee()->create();

    // Act
    $result = Invoice::monthlyFee()->get();

    // Assert
    expect($result)->toHaveCount(1);
    foreach ($result as $inv) {
        expect($inv->type)->toBe(InvoiceTypeEnum::MONTHLY_FEE);
    }
});

it('bookFee scope returns only book fee invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory(2)->for($student)->monthlyFee()->create();
    Invoice::factory()->for($student)->bookFee()->create();

    // Act
    $result = Invoice::bookFee()->get();

    // Assert
    expect($result)->toHaveCount(1);
    foreach ($result as $inv) {
        expect($inv->type)->toBe(InvoiceTypeEnum::BOOK_FEE);
    }
});

it('unpaidMonthlyFee scope combines unpaid and monthlyFee', function () {
    // Arrange
    $student = Student::factory()->create();
    $target = Invoice::factory()->for($student)->monthlyFee()->unpaid()->create();
    Invoice::factory()->for($student)->monthlyFee()->paid()->create();
    Invoice::factory()->for($student)->bookFee()->unpaid()->create();

    // Act
    $result = Invoice::unpaidMonthlyFee()->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('paidMonthlyFee scope combines paid and monthlyFee', function () {
    // Arrange
    $student = Student::factory()->create();
    $target = Invoice::factory()->for($student)->monthlyFee()->paid()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create();
    Invoice::factory()->for($student)->bookFee()->paid()->create();

    // Act
    $result = Invoice::paidMonthlyFee()->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('monthlyFeeForThisSchoolYear scope filters by active school year', function () {
    // Arrange
    $activeYear = SchoolYear::first() ?? SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create(); // Ensure this is distinct from activeYear
    $student = Student::factory()->create();

    $target = Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->create();
    Invoice::factory()->for($student)->for($inactiveYear)->monthlyFee()->create();

    // Act
    $result = Invoice::monthlyFeeForThisSchoolYear()->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('monthlyFeeForThisSchoolYear scope filters by month when provided', function () {
    // Arrange
    $activeYear = SchoolYear::first() ?? SchoolYear::factory()->active()->create();
    $student = Student::factory()->create();

    $target = Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->create([
        'month' => MonthEnum::January,
    ]);
    Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->create([
        'month' => MonthEnum::February,
    ]);

    // Act
    $result = Invoice::monthlyFeeForThisSchoolYear(month: MonthEnum::January->value)->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('monthlyFeeForThisSchoolYear scope returns empty when no active year', function () {
    // Arrange
    SchoolYear::query()->update(['is_active' => false]);
    Invoice::factory()->monthlyFee()->create();

    // Act
    $result = Invoice::monthlyFeeForThisSchoolYear()->get();

    // Assert
    expect($result)->toHaveCount(0);
});

it('activeYear scope returns invoices for active school year', function () {
    // Arrange
    $activeYear = SchoolYear::first() ?? SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create(); // Ensure this is distinct from activeYear

    Invoice::factory(2)->for($activeYear)->create();
    Invoice::factory(2)->for($inactiveYear)->create();

    // Act
    $result = Invoice::activeYear()->get();

    // Assert
    expect($result)->toHaveCount(2);

    foreach ($result as $inv) {
        expect($inv->school_year_id)->toBe($activeYear->id);
    }
});

// --- Computed Attributes ---

it('formatted_amount returns IDR currency string', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['amount' => 50000]);

    // Assert
    expect($invoice->formatted_amount)->toBeString()
        ->toContain('Rp');
});

it('formatted_fine returns IDR currency string', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['fine' => 10000]);

    // Assert
    expect($invoice->formatted_fine)->toBeString()
        ->toContain('Rp');
});

it('formatted_discount returns IDR currency string', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['discount' => 5000]);

    // Assert
    expect($invoice->formatted_discount)->toBeString()
        ->toContain('Rp');
});

it('formatted_total_amount returns IDR currency string', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['total_amount' => 100000]);

    // Assert
    expect($invoice->formatted_total_amount)->toBeString()
        ->toContain('Rp');
});

// --- Static Methods ---

it('generateFingerprint returns colon-separated string', function () {
    // Arrange & Act
    $fingerprint = Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::MONTHLY_FEE,
        'student_id' => 'student-123',
        'school_year_id' => 'year-456',
        'month' => 5,
    ]);

    // Assert
    expect($fingerprint)->toBe('1:student-123:year-456:5');
});

it('generateFingerprint throws on missing student_id', function () {
    // Act & Assert
    expect(fn () => Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::MONTHLY_FEE,
        'school_year_id' => 'year-456',
        'month' => 5,
    ]))->toThrow(InvalidArgumentException::class, 'student_id');
});

it('generateFingerprint throws on missing school_year_id', function () {
    // Act & Assert
    expect(fn () => Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::MONTHLY_FEE,
        'student_id' => 'student-123',
        'month' => 5,
    ]))->toThrow(InvalidArgumentException::class, 'school_year_id');
});

it('generateFingerprint throws on missing type', function () {
    // Act & Assert
    expect(fn () => Invoice::generateFingerprint([
        'student_id' => 'student-123',
        'school_year_id' => 'year-456',
        'month' => 5,
    ]))->toThrow(InvalidArgumentException::class, 'type');
});

it('generateFingerprint throws on missing month', function () {
    // Act & Assert
    expect(fn () => Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::MONTHLY_FEE,
        'student_id' => 'student-123',
        'school_year_id' => 'year-456',
    ]))->toThrow(InvalidArgumentException::class, 'month');
});

it('generateReferenceNumber has INV prefix with date and ULID', function () {
    // Arrange & Act
    $reference = Invoice::generateReferenceNumber();

    // Assert
    expect($reference)->toStartWith('INV/')
        ->toMatch('/^INV\/\d{8}\/[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('generateGroupReference has PAY prefix with date and ULID', function () {
    // Arrange & Act
    $reference = Invoice::generateGroupReference();

    // Assert
    expect($reference)->toStartWith('PAY/')
        ->toMatch('/^PAY\/\d{8}\/[0-9A-HJKMNP-TV-Z]{26}$/');
});

// --- calculateFineFromOldestUnpaidInvoice ---

it('calculateFineFromOldestUnpaidInvoice returns zero when no unpaid invoices', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->paid()->create();

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert
    expect($fine)->toBe(0);
});

it('calculateFineFromOldestUnpaidInvoice returns zero when due date is in future', function () {
    // Arrange
    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => now()->addDays(10),
    ]);

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert
    expect($fine)->toBe(0);
});

it('calculateFineFromOldestUnpaidInvoice calculates fine based on days late', function () {
    // Arrange
    config(['setting.fine' => 1000]);
    Carbon::setTestNow('2026-03-20');

    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => '2026-03-10',
    ]);

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert — 10 days * 1000/day = 10000
    expect($fine)->toBe(10000);
});

it('calculateFineFromOldestUnpaidInvoice uses oldest unpaid invoice', function () {
    // Arrange
    config(['setting.fine' => 500]);
    Carbon::setTestNow('2026-03-20');

    $student = Student::factory()->create();
    // Oldest — should be used for calculation
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => '2026-03-05',
    ]);
    // Newer — should NOT be used
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => '2026-03-15',
    ]);

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert — 15 days (from Mar 5) * 500/day = 7500
    expect($fine)->toBe(7500);
});

it('calculateFineFromOldestUnpaidInvoice returns zero when fine rate is zero', function () {
    // Arrange
    config(['setting.fine' => 0]);
    Carbon::setTestNow('2026-03-20');

    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => '2026-03-10',
    ]);

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert
    expect($fine)->toBe(0);
});

// --- Missing Scope Tests ---

it('unpaidBookFee scope combines unpaid and bookFee', function () {
    // Arrange
    $student = Student::factory()->create();
    $target = Invoice::factory()->for($student)->bookFee()->unpaid()->create();
    Invoice::factory()->for($student)->bookFee()->paid()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create();

    // Act
    $result = Invoice::unpaidBookFee()->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('unpaidMonthlyFeeForThisSchoolYear scope combines unpaid and monthlyFeeForThisSchoolYear', function () {
    // Arrange
    $activeYear = SchoolYear::first() ?? SchoolYear::factory()->active()->create();
    $inactiveYear = SchoolYear::factory()->inactive()->create();
    $student = Student::factory()->create();

    $target = Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->unpaid()->create([
        'month' => MonthEnum::January,
    ]);
    Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->paid()->create([
        'month' => MonthEnum::February,
    ]);
    Invoice::factory()->for($student)->for($inactiveYear)->monthlyFee()->unpaid()->create([
        'month' => MonthEnum::January,
    ]);

    // Act
    $result = Invoice::unpaidMonthlyFeeForThisSchoolYear()->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

it('unpaidMonthlyFeeForThisSchoolYear scope filters by month when provided', function () {
    // Arrange
    $activeYear = SchoolYear::first() ?? SchoolYear::factory()->active()->create();
    $student = Student::factory()->create();

    $target = Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->unpaid()->create([
        'month' => MonthEnum::March,
    ]);
    Invoice::factory()->for($student)->for($activeYear)->monthlyFee()->unpaid()->create([
        'month' => MonthEnum::April,
    ]);

    // Act
    $result = Invoice::unpaidMonthlyFeeForThisSchoolYear(month: MonthEnum::March->value)->get();

    // Assert
    expect($result)->toHaveCount(1)
        ->first()->id->toBe($target->id);
});

// --- Additional Cast Tests ---

it('casts issued_at to date', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['issued_at' => '2024-06-15']);

    // Assert
    expect($invoice->issued_at)
        ->toBeInstanceOf(Carbon::class)
        ->and($invoice->issued_at->toDateString())->toBe('2024-06-15');
});

it('casts due_date to date', function () {
    // Arrange & Act
    $invoice = Invoice::factory()->create(['due_date' => '2024-07-15']);

    // Assert
    expect($invoice->due_date)
        ->toBeInstanceOf(Carbon::class)
        ->and($invoice->due_date->toDateString())->toBe('2024-07-15');
});

// --- Additional Fingerprint Tests ---

it('generateFingerprint for BOOK_FEE excludes month when not provided', function () {
    // Arrange & Act
    $fingerprint = Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::BOOK_FEE,
        'student_id' => 'student-123',
        'school_year_id' => 'year-456',
    ]);

    // Assert — no month segment
    expect($fingerprint)->toBe('2:student-123:year-456');
});

it('generateFingerprint for BOOK_FEE includes month when provided', function () {
    // Arrange & Act
    $fingerprint = Invoice::generateFingerprint([
        'type' => InvoiceTypeEnum::BOOK_FEE,
        'student_id' => 'student-123',
        'school_year_id' => 'year-456',
        'month' => 3,
    ]);

    // Assert — includes month
    expect($fingerprint)->toBe('2:student-123:year-456:3');
});

it('rejects duplicate fingerprint on create', function () {
    // Arrange
    $student = Student::factory()->create();
    $schoolYear = SchoolYear::factory()->inactive()->create();

    Invoice::factory()->for($student)->for($schoolYear)->monthlyFee()->create([
        'month' => MonthEnum::January,
    ]);

    // Act & Assert — same student + school year + type + month = duplicate fingerprint
    expect(fn () => Invoice::factory()->for($student)->for($schoolYear)->monthlyFee()->create([
        'month' => MonthEnum::January,
    ]))->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

// --- Additional Fine Calculation Edge Case ---

it('calculateFineFromOldestUnpaidInvoice returns zero when due date is exactly today', function () {
    // Arrange
    config(['setting.fine' => 1000]);
    Carbon::setTestNow('2026-03-10');

    $student = Student::factory()->create();
    Invoice::factory()->for($student)->monthlyFee()->unpaid()->create([
        'due_date' => '2026-03-10',
    ]);

    // Act
    $fine = Invoice::calculateFineFromOldestUnpaidInvoice($student);

    // Assert — due date equals today, not late yet
    expect($fine)->toBe(0);
});
