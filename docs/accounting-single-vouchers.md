# Single vouchers

Open **Accounting → Single vouchers**. Choose the voucher type and one settlement account, then add one or more detail lines. Each line contains its ledger account, employee/person or note, and amount. The settlement total and balanced debit/credit lines are calculated automatically. These are accounting allocations; employee notes do not run payroll or calculate tax deductions.

| Type | Settlement | Detail side |
|---|---|---|
| CPV — Cash Payment Voucher | One Cash asset ledger, credit | Debit counterpart ledgers |
| CRV — Cash Receipt Voucher | One Cash asset ledger, debit | Credit counterpart ledgers |
| BPV — Bank Payment Voucher | One Bank asset ledger, credit | Debit counterpart ledgers |
| BRV — Bank Receipt Voucher | One Bank asset ledger, debit | Credit counterpart ledgers |
| EV — Expense Voucher | One asset/liability ledger, credit | Debit Expense ledgers |
| IV — Income Voucher | One asset/liability ledger, debit | Credit Income ledgers |

EV and IV are explicitly defined here as Expense Voucher and Income Voucher. The names are not asset/liability classifications. Cash/bank transfers use journals; customer/supplier control-account settlements continue through invoice payments. The selected type is fixed after saving.

Example: a BPV can contain salary expense lines for Ali (30,000), Ahmed (40,000), and Sara (35,000), with an automatically calculated Bank credit of 105,000. When salaries were already accrued, use the corresponding **General-type salary liability ledger** in CPV/BPV detail lines instead of debiting salary expense again. Receivable/Payable control types remain reserved for party transactions.

Saved drafts have no ledger effect. With approvals enabled, the whole voucher follows Finance review → CEO approval → Finance verification → Finance Manager final posting. Permissions for each type are configured in Roles and permissions. Built-in templates were updated; custom roles need explicit permission grants for new types. Company access, stale-version protection, closed periods, balanced posting and audit history remain enforced.

Cash/bank credits in CPV, BPV and EV require sufficient posted funds on the voucher date at save and final posting. Drafts do not reserve funds. Printing includes all detail notes and amounts. Corrections use existing authorised reversal controls. A multi-line cash voucher opened from the older cash screen is routed to the single-voucher editor so its detail lines cannot be flattened into one line.

Verification includes all six types, three salary allocations, exact amounts, printing, salary liability settlement without duplicate expense, insufficient bank funds, wrong account types/directions, type-specific permissions, approval enforcement and browser checks with mocked writes. Database feature-test fixtures roll back.
