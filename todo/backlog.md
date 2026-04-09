# Backlog

> Single source of truth for all pending work.
> PRDs live in `todo/prds/`. Sprint execution plans in `todo/sprints/`.

---

## Foundation — Student, School, Finance Core

> PRD: [prds/foundation-v1.md](./prds/foundation-v1.md)
> Interview: [prds/foundation-interview.md](./prds/foundation-interview.md)

### P0 — Foundation Phases (sequential, see PRD §7 for details)

- [ ] Phase A: Fee type foundation (FeeType, FeeSchedule, StudentFeeOverride, StudentVirtualAccount, OverrideTypeEnum) `#migration` `#action`
- [ ] Phase B: Invoice & payment evolution (fee_type_id on invoices, Payment model, RecordPayment action) `#migration` `#action`
- [ ] Phase C: Student model cleanup (remove fee/VA columns, update scopes, update Filament UI) `#migration` `#ui`
- [ ] Phase D: Enrollment lifecycle (transfer, promotion, alumni) `#migration` `#action`
- [ ] Phase E: Academic structure (subject category hierarchy, learning group redesign) `#migration`
- [ ] Phase F: Year-end transition workflow (wizard page, close/open year actions) `#action` `#ui`

---

## Finance Module

### P0

- [ ] Import data pembayaran uang SPP `#action`
- [ ] Import data pembayaran uang buku `#action`
- [ ] Export data pembayaran SPP untuk upload di Bank `#action`
- [ ] Export data pembayaran buku untuk upload di Bank `#action`

### P1

- [ ] Add widgets di dashboard Finance panel `#ui`
- [ ] Filter yang punya tagihan saja (student list) `#ui`
- [ ] Saat buat tagihan buku, update juga master nominal buku jika ada tambahan `#action`
- [ ] Fitur milih virtual account BCA or Mandiri `#ui`

### P2

- [ ] Cek apakah fungsi relasi BelongsToClassroom trait bentrok/duplikat dengan currentClassroom di model Student `#cleanup`
- [ ] Buat global scope auto-apply untuk filter only active student `#cleanup`
- [ ] GradeEnum::forLevel — refactor, sudah dipakai di multi tempat `#cleanup`

---

## SupplyHub — Purchase Order Flow

> Detailed in old backlog, preserved here.

### P0 — Core PO Domain

- [ ] Create PO schema: `purchase_orders`, `purchase_order_lines`, `purchase_order_line_branch_allocations`, `purchase_receipts`, `purchase_receipt_lines` `#migration`
- [ ] Add enums: `PurchaseOrderStatusEnum`, `DiscountTypeEnum`, `ReceiveModeEnum` `#migration`
- [ ] Add models: PurchaseOrder, PurchaseOrderLine, PurchaseOrderLineBranchAllocation, PurchaseReceipt, PurchaseReceiptLine `#migration`
- [ ] Build PO lifecycle actions: CreateOrUpdatePurchaseOrder, SubmitPurchaseOrder, ClosePurchaseOrderLine, ClosePurchaseOrder, CancelPurchaseOrder `#action`
- [ ] Build receipt action RecordPurchaseReceipt with partial-delivery + over-receipt validation `#action`
- [ ] Integrate receipt posting with RecordStockMovement (STOCK_IN) per receipt line and target branch `#action`
- [ ] Apply temporary latest-cost policy on receipt: update product_items.purchase_price and sale_price `#action`
- [ ] Add non-tenant-scoped SupplyHub PurchaseOrder resource (global admin-only) `#ui`
- [ ] Add PO pages: list, create, edit (draft only), view, receive `#ui`
- [ ] Build create/edit wizard: header, supplier-scoped items, discount + price, branch allocation matrix, review step `#ui`
- [ ] Show current stock per branch inline during PO planning `#ui`
- [ ] Add receive UX for repeated partial receipts and line close with reason `#ui`
- [ ] Add explicit authorization for PO access (procurement-admin capability) `#action`

### P0 — PO Test Coverage

- [ ] Test draft PO creation with mandatory supplier + school year `#test`
- [ ] Test allocation mismatch validation `#test`
- [ ] Test partial receipt status transition (SUBMITTED → PARTIALLY_RECEIVED) `#test`
- [ ] Test full receipt completion (PARTIALLY_RECEIVED → RECEIVED) `#test`
- [ ] Test over-receipt rejection `#test`
- [ ] Test line close updates PO aggregate status `#test`
- [ ] Test receipt creates stock movements and updates branch stock quantities `#test`
- [ ] Test latest-cost update behavior on product_items `#test`
- [ ] Test PO authorization boundaries `#test`

### P1 — Ops Improvements

- [ ] Add "distribute from central receipt" shortcut flow `#action`
- [ ] Add PO aging report and outstanding quantity report `#ui`
- [ ] Add price-change history report by item/supplier/PO `#ui`

### P2 — Accounting & Procurement

- [ ] Replace temporary latest-cost policy with moving-average or FIFO cost ledger `#action`
- [ ] Add supplier invoice matching (procure-to-pay phase 2) `#action`
- [ ] Add supplier payment tracking workflow `#action`

### P2 — SupplyHub Misc

- [ ] Enforce student-branch integrity for stock distribution `#action`
- [ ] Align stock adjustment UI with backend support for negative adjustment quantity `#ui`
- [ ] Replace direct stock edits with movement-based adjustments for audit trail `#cleanup`

---

## Platform / Cross-Cutting

### P1

- [ ] Pre-use setup page — Filament page shown when no active academic period exists, guide admin through SchoolYear + SchoolTerm creation `#ui`
- [ ] Student active status consistency — wrap syncActiveStatus in DB transaction, handle edge cases `#action`
- [ ] Scope hardening with qualifyColumn() — audit all custom scopes used in joins `#cleanup`
- [ ] Academic period caching — confirm cache wired into getActive(), write cache hit/miss tests `#test`

### P2

- [ ] Add missing data inside student metadata key "warning" with list of problems/warnings `#action`
- [ ] ApplyTenantScopesMiddleware for manual tenant scoping + active student scope `#action`
- [ ] GUI for data migration instead of terminal commands `#ui`

---

## Test Debt

### P1

- [ ] GenerateBulkMonthlyFeeInvoice tests `#test`
- [ ] MonthlyFeeInvoicesRelationManager tests `#test`
- [ ] Classroom scope excludeFinalYears test `#test`
- [ ] Student scope notInFinalYears test `#test`
- [ ] Student scope hasProblems test `#test`
- [ ] Student scope hasNoProblems test `#test`
- [ ] Invoice scope bookFeeForNextSchoolYear test `#test`
- [ ] GenerateBookFeeInvoice test `#test`
- [ ] GenerateMonthlyFeeInvoice action tests (4 stubs in test file) `#test`
- [ ] Invoice resource in Finance panel tests `#test`
- [ ] ListStudent tabs tests `#test`

> Note: Many of these tests will be superseded by Foundation Phase B (new GenerateInvoice action replaces old ones). Evaluate which tests are still relevant before implementing.
