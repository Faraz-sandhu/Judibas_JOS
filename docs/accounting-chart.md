# Accounting: Chart of Accounts

The Accounting product opens at `/accounting`. It reuses the existing `accounts` product slug so existing assignments remain compatible. The generic product name is now Accounting; an existing default Accounts name override is updated by the migration.

## First use

1. Run `php artisan migrate` and `npm run build` on deployment.
2. In Administration, select Accounting when creating/editing a user or sending an invitation. Assign companies and the Accounting role.
3. Open Accounting, select a company and choose **Set up chart of accounts**.
4. Review the starter groups and accounts with the accountant. Create groups and ledger accounts, update details, and deactivate accounts where appropriate.

No charts are created automatically by viewing the product. Initial setup creates only a small service-business structure, with no balances, transactions or country-specific statutory assumptions.

## Roles and boundaries

- Viewer: reads charts in assigned companies.
- Accountant: sets up and manages accounts in assigned companies.
- Accounting Manager: also deletes non-root accounts that have no children.
- Platform administrators: manage all companies, following the existing shared access model.

Roles apply across the assigned companies, like the existing shared product access forms. Removing product access, deactivating the user or removing company membership blocks server access. Existing account history is retained.

## Structure rules

Five protected root groups: Asset, Liability, Equity, Income, Expense. A child must reference a same-company group in the same root. Group/ledger kind and root classification cannot be changed after creation. Moving under oneself or a descendant is rejected. An active child requires an active parent, and groups with active children cannot be deactivated. Codes are normalized to uppercase and unique within a company. All changes lock the company row inside a database transaction, serializing conflicting chart changes and duplicate initialization. Actions retain actor/company context in accounting audit records.

## Current scope

This is account-structure management only. The ledger, balances, opening transactions, invoices, payments and financial reports are separate next steps. When ledger tables are introduced, add posting-reference restrictions before permitting account edits or deletions used in transactions.

## Verification

- `php artisan test --filter=AccountingChartTest`
- `node scripts/check-accounting-chart.mjs` (browser fixtures, no production chart writes)
- `npm run build`

## Additional integrity audit

Updates and deletion require the account version returned by the data API. A stale version receives HTTP 409 and cannot overwrite or delete a newer account. The UI refreshes the underlying chart on conflict, allowing the user to reopen the latest account.

A follow-up migration adds a composite same-company parent foreign key, one protected root per company/type, and PostgreSQL checks for valid root types, normalized codes, root/child structure, group account type and direct self-parenting. Cycle and active-parent validation remains in the serialized server transaction. These checks strengthen structure integrity; they do not certify a future ledger, financial statements or local statutory accounting.
