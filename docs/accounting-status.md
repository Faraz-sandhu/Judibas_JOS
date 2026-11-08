# Judibas Accounting implementation status

Updated 4 November 2026. Target jurisdiction: Pakistan. This is a native Laravel/Vue implementation; it does not run or embed the ERPNext accounting engine.

## Implemented

- Shared product authentication, company access, protected role templates and custom granular roles assigned through Administration users/invitations.
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
4. Fiscal years, year-end closing vouchers, retained-earnings transfer and amount-based approval limits. Document approval stages and preparer/CEO/final-poster separation are implemented as described in accounting-approvals.md.
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


## Cash vouchers ? 3 November 2026

Separate Cash Receipt Voucher (CRV) and Cash Payment Voucher (CPV) screens use the existing journal ledger, with searchable cash/counterpart accounts, person or organisation, date, amount, reference and optional description. Each simple voucher uses one cash ledger and one non-cash counterpart. Drafts have no ledger effect. Posting validates cash direction and balanced amounts; posted vouchers are immutable and managers can reverse them. Printing includes explicit draft/reversed status. Request-key protection prevents repeated submissions from duplicating the same voucher. Standalone vouchers cannot use customer/supplier control accounts or bank accounts: allocated invoice receipts/payments remain on the invoice workflow and transfers remain journals.

The separate Reports sidebar group contains Accounts Receivable and Accounts Payable only. General Ledger stays under Accounting; Trial Balance, Profit and Loss, and Balance Sheet stay under Financial reports. Receivables/payables no longer appear in Sales/Purchases.


Cash Payment Vouchers now require sufficient posted funds in the selected Cash ledger as of the voucher date, both when saving a draft and when posting. Drafts do not reserve funds. The posting check executes under the existing company-row transaction lock, rechecking after other postings. Receipts, later-dated entries and draft journals do not incorrectly contribute funds; posted reversal entries are included. Insufficient funds produce a validation message with date, available balance and payment amount. This rule is scoped to standalone Cash Payment Vouchers, including Expense paid shortcuts; it does not impose a new restriction on bank payments or general journals.


## Profit and Loss accounting basis ? 4 November 2026

Profit and Loss now provides Accrual basis (default) and Cash basis. Changing the selector reloads the report, and the statement caption and CSV identify the basis. This is a reporting choice and does not rewrite journals, company books, balances or the Balance Sheet. The Accounting Overview continues to use accrual figures.

Cash basis recognises posted invoice receipts/supplier payments on their payment dates, with proportionate allocation across the original invoice components. Taxes and capital assets are allocated their portion without being classified as income/expenses. Partial settlements use integer-only allocation, distribute rounding pennies, and fully settle original component amounts. Payment reversals undo the original allocation on the reversal date; historical reports keep the original receipt/payment until that date. Direct cash/bank income or expense journals, cash vouchers and their reversals are recognised without counting invoice/payment journals twice. Non-cash accruals, depreciation, capital contributions and pure transfers do not become profit.

Manual cash movements through general balance-sheet accounts (such as salary accruals or prepayments) and ambiguous mixed journals do not provide an explicit P&L allocation in the existing data model. Cash-basis output lists those entries as excluded and requiring review, marks cash_basis_complete false, and includes the review notes in CSV. Supported totals must not be treated as a complete cash-basis result while those entries remain unallocated. Cash statement account names do not link to the accrual General Ledger because the two amounts can differ. The review workflow below now allows authorised users to resolve these indirect manual settlements.


## Clickable cash-basis review and resolution

Cash-basis exceptions now open a read-only journal preview, including the posting date, description, account lines and net cash movement. Accounting Managers and Accountants can resolve an exception by assigning one or more income/expense accounts, separating any balance-sheet amount, or confirming that the entire movement is outside Profit and Loss. A reason is required. Refund/reduction allocations are supported. Represented cash must reconcile exactly, and gross allocations cannot exceed the amounts supported by the source journal. Automatically recognised entries and invoice/payment documents cannot be manually overridden through this workflow.

Review decisions are company-scoped, version checked and audited with previous/current values and reviewer name. Closed accounting periods cannot be reclassified. Account references are protected with foreign keys. The original journal and accrual reports are unchanged; cash-basis reports and exports apply the reviewed allocation, with the opposite values on a source reversal's own date. Resolved entries move to a clickable Reviewed cash-basis entries list and can be inspected or corrected by authorised users. Read-only users can inspect the original and the review but cannot save changes.


## Journal Save & Print

Journal drafts now offer Save & Print. The form is validated, the draft is saved, and only a successful response opens the printable saved journal. Failed saves close the pending preview window. Draft printing does not post the journal or change ledger balances. Editable drafts offer Save draft, Save & Print and Review posting; the duplicate Print saved draft action has been removed. Posted/reversed journals have Print journal. Read-only users can print an existing draft without editing it. The print view includes company, number, date, reference, description, account codes/names, line notes, debit/credit totals and signature spaces, with clear Draft/Posted/Reversed status and repeated table headings for multiple pages. Printing requires company read access, records an audit event, uses a consistent database snapshot and refuses an outdated requested journal version. If a browser blocks the preview, users are prompted to allow pop-ups and retry Save & Print.


## Roles and approvals - 4 November 2026

See [Accounting roles and approvals](accounting-approvals.md) for configuration, permissions, document scope and the Finance review -> CEO -> Finance verification -> Finance Manager posting workflow. Approval is enabled by default for company books. Pending payment requests have no ledger effect. Final posting rechecks document versions, closed periods and existing financial validations.

Verification: 68 Accounting feature tests passed (924 assertions), TypeScript/production build passed, and chart, ledger and approval browser checks passed. Browser writes use mocked APIs; feature-test financial fixtures roll back.
