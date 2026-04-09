# Sprint: Foundation Phase A — Fee Type System

**PRD:** [../prds/foundation-v1.md](../prds/foundation-v1.md) (Section 2.1, 4.1, 4.2, 7 Phase A)
**Branch:** `feature/fee-type-system`
**Status:** 🔴 Not Started
**Depends on:** Nothing (this is the first phase)
**Unlocks:** Phase B, D, E (can run in parallel after this)

---

## Context

Replace hardcoded InvoiceTypeEnum with flexible FeeType model. Move fee amounts from Student model to FeeSchedule lookup table. Add individual override support and decouple virtual accounts.

## Tasks

### Models & Migrations
- [ ] Create `fee_types` migration + `FeeType` model + factory
- [ ] Create `fee_schedules` migration + `FeeSchedule` model + factory
- [ ] Create `student_fee_overrides` migration + `StudentFeeOverride` model
- [ ] Create `student_virtual_accounts` migration + `StudentVirtualAccount` model
- [ ] Create `OverrideTypeEnum` (PercentageDiscount=1, FixedDiscount=2, FullOverride=3)
- [ ] Update `DatabaseSeeder` to create FeeType rows for "SPP" and "Buku"

### Actions
- [ ] Write `ResolveStudentFeeAmount` action (lookup FeeSchedule + apply StudentFeeOverride)
- [ ] Write `CalculateInvoiceFine` action (use FeeType.fine_cutoff_day + fine_daily_rate)

### Filament UI
- [ ] Create `FeeTypeResource` in Admin panel (CRUD for fee types)
- [ ] Create `FeeScheduleResource` in Admin panel (manage amounts per school+grade+year)

### Tests
- [ ] FeeType model tests (casts, relationships, factory)
- [ ] FeeSchedule model tests (unique constraint, relationships)
- [ ] StudentFeeOverride model tests
- [ ] StudentVirtualAccount model tests
- [ ] ResolveStudentFeeAmount action tests (base amount, percentage discount, fixed discount, full override, no schedule found)
- [ ] CalculateInvoiceFine action tests (no fine config, before cutoff, on cutoff day, after cutoff, zero rate)
- [ ] FeeTypeResource Filament tests
- [ ] FeeScheduleResource Filament tests

### Verification
- [ ] `composer analyse` passes (zero errors)
- [ ] `composer test` passes (all green)
- [ ] Manual check: create fee types and schedules via Filament UI

## Agent Instructions

1. Read `CLAUDE.md` first for coding standards
2. Read PRD §2.1 for exact table schemas
3. Read PRD §4.1 and §4.2 for action logic
4. Read existing models (`Student.php`, `Invoice.php`, `SchoolYear.php`) to understand current patterns
5. Use `php artisan make:model --migration --factory` for new models
6. Follow existing model structure: $guarded, casts(), booted(), relationships, scopes, accessors
7. All models use ULID primary keys (`HasUlids` trait)
8. Run `composer analyse` and `composer test` before marking done
