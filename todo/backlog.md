# Backlog

- [ ] instead of migrasi data di terminal, buat versi GUI aja, spya bsa lhat apa yg terjadi, migrasi perkelas, dll, pkoknya spya bsa lht data" bermasalah sblm migration
- [ ] pikirkan kembali proses kenaikan kelas, proses perpindahan tahun ajaran dan semester
- [x] buat jadi action baru untuk toggle expand table
- [ ] add a missing data inside metadata, the key is warning and inside tht maybe a json/array the list of the problem/warnings
- [ ] buat global scope auto apply untuk filter only active student
- [ ] GradeEnum::forLevel refactor ini, soalnya udh di pakai di multi tempat

## SupplyHub Follow-ups

- [ ] [P2] Enforce student-branch integrity for stock distribution.
- [ ] [P2] Align stock adjustment UI with backend support for negative adjustment quantity.
- [ ] [P3] Replace direct stock edits with movement-based adjustments for audit trail.

## Order Flow (SupplyHub Global Admin PO)

### P0 - Core Domain + Flow

- [ ] [P0] Create PO schema: `purchase_orders`, `purchase_order_lines`, `purchase_order_line_branch_allocations`, `purchase_receipts`, `purchase_receipt_lines`.
- [ ] [P0] Add enums: `PurchaseOrderStatusEnum`, `DiscountTypeEnum`, `ReceiveModeEnum`.
- [ ] [P0] Add models: `PurchaseOrder`, `PurchaseOrderLine`, `PurchaseOrderLineBranchAllocation`, `PurchaseReceipt`, `PurchaseReceiptLine`.
- [ ] [P0] Build PO lifecycle actions: `CreateOrUpdatePurchaseOrder`, `SubmitPurchaseOrder`, `ClosePurchaseOrderLine`, `ClosePurchaseOrder`, `CancelPurchaseOrder`.
- [ ] [P0] Build receipt action `RecordPurchaseReceipt` with partial-delivery support and over-receipt validation.
- [ ] [P0] Integrate receipt posting with `RecordStockMovement` (`STOCK_IN`) per receipt line and target branch.
- [ ] [P0] Apply temporary latest-cost policy on receipt: update `product_items.purchase_price` and `product_items.sale_price`.
- [ ] [P0] Add non-tenant-scoped SupplyHub `PurchaseOrder` resource (global admin-only).
- [ ] [P0] Add PO pages: list, create, edit (draft only), view, receive.
- [ ] [P0] Build create/edit wizard: header, supplier-scoped items, discount + price input, required branch allocation matrix, review step.
- [ ] [P0] Show current stock per branch inline during PO planning.
- [ ] [P0] Add receive UX for repeated partial receipts and line close with reason.
- [ ] [P0] Add explicit authorization for PO access (procurement-admin capability).

### P1 - Ops Improvements

- [ ] [P1] Add "distribute from central receipt" shortcut flow to transfer operations.
- [ ] [P1] Add PO aging report and outstanding quantity report.
- [ ] [P1] Add price-change history report by item/supplier/PO.

### P2 - Accounting and Procurement Expansion

- [ ] [P2] Replace temporary latest-cost policy with moving-average or FIFO cost ledger.
- [ ] [P2] Add supplier invoice matching (procure-to-pay phase 2).
- [ ] [P2] Add supplier payment tracking workflow.

### Test Coverage

- [ ] [P0] Test draft PO creation with mandatory supplier + school year.
- [ ] [P0] Test allocation mismatch validation (sum(branch planned qty) must equal line ordered qty).
- [ ] [P0] Test partial receipt status transition (`SUBMITTED` -> `PARTIALLY_RECEIVED`).
- [ ] [P0] Test full receipt completion (`PARTIALLY_RECEIVED` -> `RECEIVED`).
- [ ] [P0] Test over-receipt rejection.
- [ ] [P0] Test line close updates PO aggregate status.
- [ ] [P0] Test receipt creates stock movements and updates branch stock quantities.
- [ ] [P0] Test latest-cost update behavior on `product_items`.
- [ ] [P0] Test PO authorization boundaries (non-procurement users denied).
