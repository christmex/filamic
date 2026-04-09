# PRD: Filamic Foundation — Student, School, Classroom & Finance Core

**Version**: 1.0
**Date**: 2026-04-08
**Status**: Draft
**Author**: Developer + Claude (AI architect)
**Interview**: [foundation-interview.md](./foundation-interview.md)

---

## 1. Overview

### 1.1 What is Filamic?

A multi-branch school management system for Indonesian schools (Batam area). Built with Laravel 12, Filament 5, Livewire 4. Manages academics, student enrollment, finance, inventory (SupplyHub), and content (CMS) across 2-5 branches.

### 1.2 Why This PRD?

The current codebase has a working finance module but it's hardcoded to 2 fee types (SPP/monthly and Book fees). Fee amounts and virtual accounts live directly on the Student model. This doesn't scale — the school needs flexible fee types, proper enrollment lifecycle, cross-branch transfers, and a year-end transition workflow. This PRD redesigns the foundation to support all of that.

### 1.3 Current State

| Area | Status | Problem |
|------|--------|---------|
| Fee types | Hardcoded `InvoiceTypeEnum` (MONTHLY_FEE, BOOK_FEE) | Can't add new fee types without code changes |
| Fee amounts | `monthly_fee_amount`, `book_fee_amount` on Student model | Can't vary by school/grade, no schedule table |
| Virtual accounts | `monthly_fee_virtual_account`, `book_fee_virtual_account` on Student | Tied to fee type, can't handle cross-branch transfer |
| Payments | Inline on Invoice (`paid_at`, `payment_method`) | One payment = one invoice only, no lump payments |
| Fines | Global `config('setting.fine')` daily rate | No configurable cutoff day, no per-fee-type config |
| Enrollment | Basic (student ↔ classroom ↔ school_year) | No mid-year date, no transfer tracking, no promotion workflow |
| Subject categories | Flat structure | Need nested/hierarchical categories |
| Learning groups | Per-school only, no year scoping | Need cross-class, year-scoped groups |
| Year-end transition | Manual | Need guided workflow |

### 1.4 Scope

**In scope:**
- Flexible fee type system (FeeType, FeeSchedule, StudentFeeOverride)
- Virtual account decoupling from Student model
- Payment model (single payment → multiple invoices)
- Fine configuration per fee type (cutoff day + daily rate)
- Enrollment lifecycle (mid-year, transfers, bulk promotion, alumni)
- Subject category hierarchy
- Learning group redesign (cross-class, year-scoped)
- Year-end transition guided workflow

**Out of scope (future phases):**
- Parent/guardian model
- Admission pipeline
- Library module
- Attendance module
- Student report cards
- Payment gateway integration (architecture prepared, not implemented)
- Role-based access control / permissions system

---

## 2. Data Model

### 2.1 New Tables

#### `fee_types` — Admin-defined fee categories

| Column | Type | Notes |
|--------|------|-------|
| id | ulid PK | |
| name | string | Display name: "SPP", "Buku", "Kegiatan", "Seragam" |
| code | string unique | Machine key: "monthly_fee", "book_fee", "activity_fee" |
| is_recurring | boolean | true = monthly recurring, false = one-time |
| fine_daily_rate | unsigned int, default 0 | Rp per day late |
| fine_cutoff_day | unsigned tinyint, nullable | Day of month (1-28). null = no fine for this type |
| is_active | boolean, default true | |
| sort_order | unsigned tinyint, default 0 | |
| created_at / updated_at | timestamps | |

**Key behaviors:**
- Organization-wide (not branch-scoped)
- Seeder must create "SPP" (code: monthly_fee, recurring, fine config) and "Buku" (code: book_fee, one-time, no fine) to match current data
- Admin can create new fee types via Filament Admin panel
- `is_recurring` determines whether invoices are generated monthly or once per year

#### `fee_schedules` — Base fee amount per school + grade + year

| Column | Type | Notes |
|--------|------|-------|
| id | ulid PK | |
| fee_type_id | FK → fee_types | |
| school_id | FK → schools | |
| grade | unsigned tinyint | GradeEnum backed value |
| school_year_id | FK → school_years | |
| amount | unsigned int | Base amount in IDR (Rupiah) |
| is_active | boolean, default true | |
| created_at / updated_at | timestamps | |

**Constraints:** unique(fee_type_id, school_id, grade, school_year_id)

**Key behaviors:**
- One row per fee-type + school + grade + school-year combination
- Fee can change mid-year: admin updates the amount, future invoices use new value
- During year-end transition, fee schedules are copied from old year to new year (admin can adjust)
- If no FeeSchedule exists for a student's school+grade+fee_type, that student is not eligible for that fee

#### `student_fee_overrides` — Individual discounts/scholarships

| Column | Type | Notes |
|--------|------|-------|
| id | ulid PK | |
| student_id | FK → students | |
| fee_type_id | FK → fee_types | |
| school_year_id | FK → school_years | |
| override_type | unsigned tinyint | OverrideTypeEnum |
| override_value | unsigned int | Depends on type (see below) |
| reason | string, nullable | "Sibling discount", "Scholarship", etc. |
| created_at / updated_at | timestamps | |

**Constraints:** unique(student_id, fee_type_id, school_year_id)

**OverrideTypeEnum:**
- `PercentageDiscount (1)` — override_value = percentage (e.g., 50 means 50% off)
- `FixedDiscount (2)` — override_value = Rp amount to subtract
- `FullOverride (3)` — override_value = replacement amount (ignores FeeSchedule)

**Fee resolution formula:**
```
base = FeeSchedule.amount for (fee_type, school, grade, school_year)
if override exists:
  PercentageDiscount: final = base * (1 - value/100)
  FixedDiscount:      final = base - value
  FullOverride:       final = value
else:
  final = base
return max(0, final)
```

#### `student_virtual_accounts` — Payment identity per student per branch

| Column | Type | Notes |
|--------|------|-------|
| id | ulid PK | |
| student_id | FK → students | |
| branch_id | FK → branches | |
| virtual_account_number | string unique | |
| is_active | boolean, default true | |
| created_at / updated_at | timestamps | |

**Constraints:** unique(student_id, branch_id)

**Key behaviors:**
- One VA per student per branch (simplified from current per-fee-type VAs)
- On cross-branch transfer: old VA deactivated (is_active=false), new VA created at new branch
- Ready for future payment gateway: gateway callback matches VA → student → branch

#### `payments` — Payment transactions

| Column | Type | Notes |
|--------|------|-------|
| id | ulid PK | |
| branch_id | FK → branches | |
| student_id | FK → students | |
| reference_number | string unique | Format: "PAY/YYYYMMDD/ULID" |
| payment_method | unsigned tinyint | PaymentMethodEnum |
| amount | unsigned int | Total cash/transfer received |
| gateway_reference | string, nullable | For future payment gateway |
| gateway_status | string, nullable | For future gateway callback status |
| notes | text, nullable | |
| paid_at | datetime | When payment was actually made |
| created_at / updated_at | timestamps | |

**Key behaviors:**
- One Payment can cover multiple invoices (via pivot table)
- gateway_reference and gateway_status are placeholders for future payment gateway integration
- reference_number generated on creation (same pattern as Invoice)

#### `invoice_payment` — Pivot: which invoices a payment covers

| Column | Type | Notes |
|--------|------|-------|
| payment_id | FK → payments | |
| invoice_id | FK → invoices | |
| amount_applied | unsigned int | Portion of payment allocated to this invoice |
| fine_applied | unsigned int, default 0 | Fine amount for this invoice |
| discount_applied | unsigned int, default 0 | Discount for this invoice |

**Key behaviors:**
- Enables one payment → many invoices
- amount_applied + fine_applied - discount_applied = actual amount deducted for this invoice
- Sum of all amount_applied for a payment should equal payment.amount

### 2.2 Modified Tables

#### `invoices` — Replace type enum with fee_type FK

| Change | Column | Notes |
|--------|--------|-------|
| ADD | fee_type_id (FK → fee_types) | Replaces `type` column |
| REMOVE | type | Was InvoiceTypeEnum, now replaced by fee_type_id |

**Fingerprint generation changes:**
```
Old: type + student_id + school_year_id + month
New: fee_type_id + student_id + school_year_id + month
```

**Keep denormalized fields**: `status`, `paid_at`, `fine`, `discount`, `total_amount`, `payment_method` stay on Invoice for fast queries and reporting. The Payment model is the source of truth, but these fields are updated in the same transaction.

#### `students` — Remove fee/VA columns

| Change | Column | Notes |
|--------|--------|-------|
| REMOVE | monthly_fee_virtual_account | → student_virtual_accounts table |
| REMOVE | book_fee_virtual_account | → student_virtual_accounts table |
| REMOVE | monthly_fee_amount | → fee_schedules table |
| REMOVE | book_fee_amount | → fee_schedules table |

**`syncActiveStatus()` changes:**
- Old: requires `monthly_fee_virtual_account` + `monthly_fee_amount > 0` + current enrollment
- New: requires current enrollment only. Fee readiness is a separate concern checked during invoice generation, not part of "is_active"

#### `student_enrollments` — Add lifecycle fields

| Change | Column | Notes |
|--------|--------|-------|
| ADD | enrolled_at (date, nullable) | Actual start date. null = start of school year |
| ADD | graduated_at (date, nullable) | Set when status → GRADUATED |
| ADD | transfer_from_enrollment_id (FK → student_enrollments, nullable) | Links to previous enrollment for transfers |
| ADD | transfer_notes (text, nullable) | Reason/notes for transfer |

#### `learning_groups` — Add year scoping and subject link

| Change | Column | Notes |
|--------|--------|-------|
| ADD | school_year_id (FK → school_years) | Year-scoped groups |
| ADD | school_term_id (FK → school_terms, nullable) | Optional term scoping |
| ADD | subject_id (FK → subjects, nullable) | Optional link to subject |
| ADD | description (text, nullable) | |

#### `subject_categories` — Add hierarchy

| Change | Column | Notes |
|--------|--------|-------|
| ADD | parent_id (FK → subject_categories, nullable) | Self-referencing for nesting |

---

## 3. Enum Changes

### 3.1 New Enum: `OverrideTypeEnum`

```php
enum OverrideTypeEnum: int implements HasColor, HasIcon, HasLabel
{
    case PercentageDiscount = 1;
    case FixedDiscount = 2;
    case FullOverride = 3;
}
```

### 3.2 Modified: `StudentEnrollmentStatusEnum`

Add cases (values 7-8):
```php
case TRANSFERRED_OUT = 7;  // Label: "Mutasi Keluar"
case DROPPED_OUT = 8;      // Label: "Putus Sekolah"
```

Update `getActiveStatuses()` — still only `[ENROLLED]`.

### 3.3 Deprecated: `InvoiceTypeEnum`

Replaced by `FeeType` model. Remove after all references are migrated.

---

## 4. Actions (Business Logic)

### 4.1 `ResolveStudentFeeAmount`

**Purpose:** Calculate the fee amount for a student, accounting for FeeSchedule base + StudentFeeOverride.

**Input:** Student, FeeType, SchoolYear
**Output:** int (amount in IDR, minimum 0)
**Logic:** See fee resolution formula in §2.1 (student_fee_overrides section)

### 4.2 `CalculateInvoiceFine`

**Purpose:** Calculate fine for a specific invoice based on fee type config.

**Input:** Invoice (must have fee_type loaded)
**Output:** int (fine amount in IDR)
**Logic:**
1. Get `fee_type.fine_cutoff_day` and `fee_type.fine_daily_rate`
2. If either is null/0, return 0
3. `cutoff_date` = issued_at's year-month + cutoff_day
4. If today <= cutoff_date, return 0
5. `days_late` = days between cutoff_date and today
6. Return `days_late * fine_daily_rate`

### 4.3 `GenerateInvoice` (replaces GenerateMonthlyFeeInvoice, GenerateBookFeeInvoice, GenerateBulkMonthlyFeeInvoice)

**Purpose:** Generate invoices for a fee type, for eligible students.

**Input:** Branch, FeeType, SchoolYear, month (nullable, required if recurring), specific student IDs (optional for single/bulk)
**Output:** Collection of created Invoices
**Logic:**
1. Get eligible students:
   - Has active enrollment in this school year at this branch
   - Has FeeSchedule for their school + grade + fee_type
   - If recurring + month: enrolled_at <= month (mid-year check)
   - Not in final years (if applicable per fee type config)
   - No existing invoice with same fingerprint (idempotency)
2. For each student:
   - Resolve fee amount via `ResolveStudentFeeAmount`
   - Create Invoice with fee_type_id, month, amount, fingerprint
3. Return created invoices

### 4.4 `RecordPayment` (replaces PayMonthlyFeeInvoice, PayBookFeeInvoice)

**Purpose:** Record a payment covering one or multiple invoices.

**Input:** Student, invoice_ids[], payment_method, paid_at, per-invoice discounts (optional), notes
**Output:** Payment
**Logic:**
1. Lock selected invoices (`lockForUpdate`)
2. Validate all invoices belong to student and are UNPAID
3. For each invoice: calculate fine via `CalculateInvoiceFine`
4. Calculate total = sum(invoice.amount + fine - discount) for all
5. Create Payment record (total amount, method, paid_at)
6. For each invoice:
   - Create `invoice_payment` pivot (amount_applied, fine_applied, discount_applied)
   - Update invoice: status=PAID, paid_at, fine, discount, total_amount, payment_method
7. Return Payment

### 4.5 `TransferStudent`

**Purpose:** Transfer a student within-branch or cross-branch.

**Input:** Student, destination (branch_id, school_id, classroom_id), notes
**Output:** void (throws on validation failure)
**Preconditions:**
- Student has NO unpaid invoices (hard block — must settle first)
- Destination classroom exists and belongs to destination school/branch
**Logic:**
1. Mark current enrollment: status = TRANSFERRED_OUT
2. Create new StudentEnrollment:
   - destination branch/school/classroom
   - school_year_id = current active
   - status = ENROLLED
   - enrolled_at = today
   - transfer_from_enrollment_id = old enrollment
   - transfer_notes = notes
3. If cross-branch: deactivate old StudentVirtualAccount
4. Run student.syncActiveStatus()

### 4.6 `BulkPromoteStudents`

**Purpose:** Year-end mass promotion with per-student exceptions.

**Input:** SchoolYear $from, SchoolYear $to, array of rules [{student_id, action, target_classroom_id}]
**Actions:** promote | hold_back | skip | graduate
**Logic:**
1. DB::transaction
2. For each student rule:
   - Get enrollment in $fromYear
   - Set old enrollment terminal status (PROMOTED, STAYED, or GRADUATED)
   - If GRADUATED: set graduated_at, mark student is_active=false
   - Otherwise: create new enrollment in $toYear with target classroom
3. Sync all affected students' active status

### 4.7 `ValidateYearEndReadiness`

**Purpose:** Pre-transition check — what issues exist before closing the year.

**Input:** SchoolYear
**Output:** Report (enrolled count, students with unpaid invoices, students without enrollment, etc.)
**Logic:** Read-only queries, returns structured data for the wizard UI.

### 4.8 `CloseSchoolYear`

**Purpose:** Deactivate the current school year and term.

**Input:** SchoolYear
**Logic:**
1. Set SchoolYear.is_active = false
2. Set associated SchoolTerms.is_active = false

### 4.9 `ActivateNewSchoolYear`

**Purpose:** Activate a new school year.

**Input:** SchoolYear
**Logic:**
1. Set SchoolYear.is_active = true
2. Set first SchoolTerm.is_active = true
3. Sync all students with enrollments in new year

### 4.10 `GenerateNewYearFeeSchedules`

**Purpose:** Copy fee schedules from old year to new year with optional amount adjustments.

**Input:** SchoolYear $from, SchoolYear $to, optional adjustments
**Logic:**
1. Get all FeeSchedules for $from year
2. For each: create new row for $to year (same fee_type, school, grade)
3. Apply adjustments if provided (percentage increase, etc.)

---

## 5. Services

### 5.1 `PromotionService`

**Purpose:** Shared logic for generating and previewing promotion batches.

**Methods:**
- `getPromotionPreview(SchoolYear $from): Collection` — Returns all enrolled students with suggested next classroom, flags G12 as "graduate"
- Used by both the Filament wizard UI (preview) and `BulkPromoteStudents` action (execution)

---

## 6. Filament UI Changes

### 6.1 New Admin Resources

- **FeeTypeResource** — CRUD for fee types (name, code, recurring, fine config)
- **FeeScheduleResource** — Manage fee amounts per school + grade + year (bulk-editable table)
- **StudentFeeOverrideResource** — Manage individual student discounts (inline on Student view or standalone)

### 6.2 Updated Finance Panel

- **StudentResource** — Remove hardcoded SPP/Book fee references. Show invoices grouped by fee type.
- **InvoiceResource** — Filter by fee_type instead of type enum. Use FeeType model for badges/colors.
- **PaymentResource** (new) — View payment history, which invoices each payment covered.
- **Pay action** — Unified single action that works with any fee type. Replaces separate "Pay Monthly Fee" and "Pay Book Fee" actions.

### 6.3 Year-End Transition Page

Custom Filament page with Wizard component:
1. **Readiness Check** — Shows report from `ValidateYearEndReadiness`
2. **Promotion Preview** — Editable table from `PromotionService`, admin can change actions per student
3. **Execute Promotion** — Runs `BulkPromoteStudents`
4. **Close Old Year** — Runs `CloseSchoolYear`
5. **Activate New Year** — Runs `ActivateNewSchoolYear`
6. **Fee Schedule Setup** — Runs `GenerateNewYearFeeSchedules`, admin reviews/adjusts

---

## 7. Implementation Phases

Each phase is independently deployable and testable. Phases should be done sequentially (each builds on the previous).

### Phase A: Fee Type Foundation
**Estimated scope:** 5 new models, 6 migrations, 1 enum, 2 actions, tests

| # | Task | Type |
|---|------|------|
| A1 | Create `fee_types` migration, model, factory, seeder | Migration + Model |
| A2 | Create `fee_schedules` migration, model, factory | Migration + Model |
| A3 | Create `student_fee_overrides` migration, model, `OverrideTypeEnum` | Migration + Model + Enum |
| A4 | Create `student_virtual_accounts` migration, model | Migration + Model |
| A5 | Write `ResolveStudentFeeAmount` action + tests | Action |
| A6 | Write `CalculateInvoiceFine` action + tests | Action |
| A7 | Create FeeType admin resource in Filament | UI |
| A8 | Create FeeSchedule admin resource in Filament | UI |

### Phase B: Invoice & Payment Evolution
**Estimated scope:** 2 migrations (modify + new), 1 model, 2 actions, tests

| # | Task | Type |
|---|------|------|
| B1 | Modify `invoices` migration: add `fee_type_id`, remove `type` | Migration |
| B2 | Update `Invoice` model: fee_type relationship, fingerprint, scopes | Model |
| B3 | Create `payments` migration + model | Migration + Model |
| B4 | Create `invoice_payment` pivot migration | Migration |
| B5 | Write `GenerateInvoice` action (replaces 3 old actions) + tests | Action |
| B6 | Write `RecordPayment` action (replaces 2 old actions) + tests | Action |
| B7 | Remove deprecated actions: PayMonthlyFeeInvoice, PayBookFeeInvoice, GenerateMonthlyFeeInvoice, GenerateBulkMonthlyFeeInvoice, GenerateBookFeeInvoice | Cleanup |
| B8 | Remove `InvoiceTypeEnum` | Cleanup |

### Phase C: Student Model Cleanup
**Estimated scope:** 1 migration modify, model updates, Filament updates

| # | Task | Type |
|---|------|------|
| C1 | Modify `students` migration: remove fee amount + VA columns | Migration |
| C2 | Update `Student` model: remove old scopes, update syncActiveStatus | Model |
| C3 | Update Finance panel StudentResource: use FeeType-based UI | UI |
| C4 | Update Finance panel InvoiceResource: filter by fee_type | UI |
| C5 | Create PaymentResource in Finance panel | UI |
| C6 | Update Student-related tests | Tests |

### Phase D: Enrollment Lifecycle
**Estimated scope:** 1 migration modify, 2 actions, 1 service, enum update, tests

| # | Task | Type |
|---|------|------|
| D1 | Modify `student_enrollments` migration: add lifecycle fields | Migration |
| D2 | Update `StudentEnrollmentStatusEnum`: add TRANSFERRED_OUT, DROPPED_OUT | Enum |
| D3 | Update `StudentEnrollment` model with new fields + relationships | Model |
| D4 | Write `TransferStudent` action + tests | Action |
| D5 | Write `BulkPromoteStudents` action + tests | Action |
| D6 | Write `PromotionService` + tests | Service |
| D7 | Add alumni scope to Student model | Model |

### Phase E: Academic Structure
**Estimated scope:** 2 migration modifications, model updates

| # | Task | Type |
|---|------|------|
| E1 | Modify `subject_categories` migration: add parent_id | Migration |
| E2 | Update `SubjectCategory` model: hierarchy methods (parent, children, descendants) | Model |
| E3 | Modify `learning_groups` migration: add school_year_id, subject_id, description | Migration |
| E4 | Update `LearningGroup` model: year-scoped relationships | Model |
| E5 | Update Filament admin resources for subject categories (tree view) | UI |
| E6 | Write tests for hierarchy and learning group changes | Tests |

### Phase F: Year-End Workflow
**Estimated scope:** 4 actions, 1 Filament wizard page

| # | Task | Type |
|---|------|------|
| F1 | Write `ValidateYearEndReadiness` action + tests | Action |
| F2 | Write `CloseSchoolYear` action + tests | Action |
| F3 | Write `ActivateNewSchoolYear` action + tests | Action |
| F4 | Write `GenerateNewYearFeeSchedules` action + tests | Action |
| F5 | Build year-end transition wizard page in Filament | UI |
| F6 | Integration tests for full year-end workflow | Tests |

---

## 8. Migration Strategy

Since the project is **pre-production** (development only, no shared database), all migrations can be edited directly:
- Modify existing migration files for schema changes
- Run `php artisan migrate:fresh --seed` to reset
- No need for forward-only migrations

**Data seeder must be updated** to create:
- FeeType rows for "SPP" and "Buku"
- FeeSchedule rows for each school + grade combination
- StudentVirtualAccount rows (migrated from Student columns)

---

## 9. Testing Strategy

Every phase must pass `composer analyse` (Pint + PHPStan) and `composer test` (Pest) before moving to next phase.

**Per Action:** Unit test covering:
- Happy path
- Validation failures (e.g., TransferStudent with unpaid invoices)
- Edge cases (e.g., CalculateInvoiceFine on the cutoff day itself)
- Database transaction rollback on failure

**Per Model:** Test covering:
- Casts (enum casts, date casts)
- Relationships
- Scopes
- Boot events / computed fields

**Per Filament Resource:** Livewire::test() covering:
- List/create/edit pages render
- Form validation
- Table columns and filters
- Custom actions (pay, transfer, promote)

---

## 10. Agent Instructions

This PRD is designed to be consumed by Claude Code agents. Each phase (A-F) can be assigned to a separate agent session. When spawning an agent for a phase:

1. Point the agent to this PRD file: `todo/prd-foundation-v1.md`
2. Point to the interview for context: `todo/architecture-interview-2026-04-08.md`
3. Point to `CLAUDE.md` for coding standards
4. Specify which phase to implement (e.g., "Implement Phase A from the PRD")
5. The agent should read all files listed in the phase before writing any code
6. The agent must run `composer analyse` and `composer test` before marking phase complete

**Dependencies between phases:**
```
A (fee types) → B (invoices) → C (student cleanup)
A → D (enrollment) [independent of B/C]
A → E (academic) [independent of B/C/D]
D → F (year-end workflow)
```

Phases A is the prerequisite for everything. After A is complete:
- B and D can run in parallel (independent)
- E can run in parallel with B and D
- C depends on B completing
- F depends on D completing
