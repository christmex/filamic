# ADR 001 — Student Active Status Depends on Enrollment Only

**Date**: 2026-04-09  
**Status**: Accepted  
**Decider**: Solo dev

---

## Context

`Student.is_active` previously depended on two conditions:
1. Student has an active enrollment in the current school year
2. `monthly_fee_virtual_account` is filled **and** `monthly_fee_amount > 0`

This coupled financial setup with enrollment status. A student could be fully enrolled but show as "Tidak Aktif" simply because their payment account hadn't been configured yet — which blocked the admin from seeing them in the "Aktif" tab.

Additionally, the 4 payment-related columns (`monthly_fee_virtual_account`, `book_fee_virtual_account`, `monthly_fee_amount`, `book_fee_amount`) were stored directly on the `students` table, which is wrong — fee configuration belongs on a separate `student_payment_accounts` table that supports multiple VAs per student per branch.

---

## Decision

**`is_active = has a current enrollment`**

Payment readiness is a separate concern from enrollment status:
- A student becomes **active** as soon as they have an active enrollment in the current school year.
- A student becomes **inactive** when their enrollment ends (or no active school year exists).
- Payment account configuration is required for **invoice generation** — it does not affect activation.

The 4 fee/VA columns are removed from `students`. Invoice generation actions are guarded with a `RuntimeException` until the `student_payment_accounts` table is built.

---

## Changes Made

| File | Change |
|------|--------|
| `database/migrations/2026_01_28_120928_create_students_table.php` | Removed 4 fee/VA column definitions |
| `app/Models/Student.php` | Rewrote `syncActiveStatus()` and `getMissingData()`; commented out 5 payment-dependent scopes |
| `database/factories/StudentFactory.php` | Removed 4 fee/VA fields from `definition()`; commented out `withoutMonthlyFee()` / `withoutBookFee()` |
| `tests/Feature/Models/StudentTest.php` | Replaced old payment-coupled sync tests with 4 enrollment-only tests |
| `app/Actions/GenerateMonthlyFeeInvoice.php` | RuntimeException guard at top of `handle()` |
| `app/Actions/GenerateBookFeeInvoice.php` | RuntimeException guard at top of `handle()` |
| `app/Actions/GenerateBulkMonthlyFeeInvoice.php` | RuntimeException guard at top of `handle()` |
| `app/Filament/Finance/Resources/Students/Schemas/StudentForm.php` | Removed 4 fee/VA form fields + Callout |
| `app/Filament/Finance/Resources/Students/Tables/StudentsTable.php` | Removed VA search column + updated placeholder |
| `app/Filament/Finance/Resources/Students/Pages/ListStudents.php` | "Aktif" tab now uses `active()` only (removed `hasNoProblems()`) |
| `resources/views/filament/finance/resources/students/tables/column.blade.php` | Removed VA grid block |
| `app/Console/Commands/MigrateLegacyData.php` | Commented out 4 fee/VA writes in `migratePaymentAccounts()` |

---

## New `syncActiveStatus()` Logic

```php
public function syncActiveStatus(): void
{
    $enrollment = $this->currentEnrollment;

    $this->updateQuietly([
        'is_active'    => filled($enrollment),
        'branch_id'    => $enrollment?->branch_id ?? $this->branch_id,
        'school_id'    => $enrollment?->school_id ?? $this->school_id,
        'classroom_id' => $enrollment?->classroom_id ?? $this->classroom_id,
    ]);
}
```

---

## Consequences

**Positive:**
- Students are correctly activated as soon as they're enrolled — no more confusing "Tidak Aktif" for enrolled students.
- Finance and academics are decoupled. Adding a student no longer requires knowing their VA numbers upfront.
- The "Aktif" tab in the Finance panel now shows all enrolled students, not just those with payment accounts.

**Negative / Trade-off accepted:**
- Students enrolled but without payment accounts show as "Aktif" but cannot generate invoices.
- Invoice generation is fully disabled until `student_payment_accounts` is implemented (RuntimeException guards block all callers).

---

## What's Deferred

- `student_payment_accounts` table: stores VA numbers and fee amounts per student per branch, supporting multiple accounts (BCA + Mandiri).
- `FeeSchedule` model: base fee amounts per school/grade/fee-type/school-year.
- `StudentFeeOverride` model: per-student discount/override.
- Remove RuntimeException guards when `student_payment_accounts` is in place.

See: `todo/prds/foundation-v1.md` — Phase A (Fee Types) and Phase C (Student Model Cleanup).
