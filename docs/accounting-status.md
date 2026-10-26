# Judibas Accounting implementation status

Updated 21 October 2026. Target jurisdiction: Pakistan. This is a native Laravel/Vue implementation; it does not run or embed the ERPNext accounting engine.

## Implemented

- Shared product authentication and company access; manager, accountant and viewer roles.
- Company chart with protected roots, versioned edits, structural constraints and audit history.
- Explicit books setup with two-decimal base currency, start date and forward-only period locking. PKR is the initial selection; no books are silently initialized.
- Balanced general journals and balance-sheet opening entries, draft/save/post workflow, version checks and duplicate request protection.
- Integer minor-unit calculations. Posted journal lines are the ledger; reports exclude drafts and include original and offsetting reversal entries.
- Database guards against editing/deleting posted journals and lines, unbalanced posting, invalid party control lines, and changing posted invoice/payment values.
- Customer and supplier contacts, versioned edits and historical invoice contact snapshots.
- Sales and non-stock purchase invoices, quantity/rate calculation, configurable single exclusive tax rate, controlled posting to receivable/payable accounts.
- Partial/full payments allocated to one invoice, overpayment rejection, cash/bank posting, payment reversal and invoice reversal after payments are reversed.
- Trial Balance, General Ledger with opening and running balances, period Profit and Loss, as-of Balance Sheet with accumulated earnings, historical receivable/payable aging.
- Paginated transaction lists and ledger, CSV report exports, printable invoices with browser save-to-PDF, audit history and role-based controls.

## Accounting boundaries

Invoice quantity and rate support two decimal places. Tax is a manually approved single exclusive percentage per invoice, rounded half-up in minor units. A configured percentage is not a declaration of legal applicability. No universal Pakistan tax rate is installed.

General journals cannot bypass party allocation by posting directly to receivable/payable control accounts. Invoice payments cannot be independently reversed through their journal screens. Corrections preserve original records and post opposite entries in an open period. Books start date and currency become fixed when a journal exists. Period locks cannot be reopened through the current UI.

Balance Sheet earnings are cumulative through the report date. Period closing vouchers and fiscal-year profit transfer are not implemented. The cash flow statement is not available. Current journals represent a single base currency, not foreign-currency transactions.

## Remaining scope before ERPNext accounting parity

1. Pakistan business tax profile, federal/provincial jurisdiction, effective-date tax templates, item-specific/multiple/inclusive taxes, withholding, statutory invoice data and applicable FBR integration. Finance acceptance is required for the actual business rules.
2. Credit/debit notes, sales/purchase returns, payment advances, multi-invoice allocations, unallocated payments, refunds, write-offs and party opening balances.
3. Bank statement import, matching/reconciliation, bank clearance dates, reconciliation controls and cheque management.
4. Fiscal years, year-end closing vouchers, retained-earnings transfer, formal approvals, approval limits and segregation of posting duties.
5. Cost centers, accounting dimensions, project accounting and budgets.
6. Foreign currencies, exchange rates, realized/unrealized exchange differences and revaluation.
7. Fixed assets, depreciation, disposal, deferred revenue/expense and recurring documents.
8. Inventory valuation, stock ledger integration and perpetual inventory posting; purchase invoices currently do not update inventory.
9. Cash flow, comparative statements, party statements, statutory reports, PDF/Excel report exports and consolidated multi-company reporting.
10. Production concurrency/load tests, independent accountant reconciliation and user acceptance with representative Pakistan transactions.

These gaps are intentionally documented. The module must not be described as complete ERPNext parity or certified Pakistan tax compliance.

## Validation

Automated tests use database transactions and roll back their fixtures. The browser scripts mock API writes so they do not create financial records in a real company's books.

- `php artisan test --filter="Accounting|PlatformUserAccessTest|PortalTest"`
- `npm run build`
- `node scripts/check-accounting-chart.mjs`
- `node scripts/check-accounting-ledger.mjs`

Reference behavior: https://docs.frappe.io/erpnext/accounting-entries and https://docs.frappe.io/erpnext/immutable-ledger-in-erpnext.
Pakistan tax jurisdiction reference: https://urdu.fbr.gov.pk/pr/fm-to-launch-fbrs-national-sales-tax-return/173355.
