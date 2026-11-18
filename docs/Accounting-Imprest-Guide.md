# Imprest / Petty Cash

## Assigning an officer

1. In Administration → Users, grant Accounting access for the relevant company and select **Junior Finance Officer**. Existing Finance Officer / Accountant roles also have Imprest Create permission. Custom roles need Imprest View and Create.
2. An Accounting Manager, Senior Finance Manager, or authorised administrator opens that company → Accounting → **Imprest / Petty Cash**.
3. Select **Assign officer fund**, choose the officer and an active Asset group (normally Current Assets), and name the fund. This creates a separate base-currency Cash ledger with an automatic code and zero balance.
4. Assignment restricts that officer's Accounting mutations for this company to their own fund, even if their previous Accounting role had broader voucher permissions. Assigning companies and roles remains in central Administration.

## Example: company cash 200,000; officer allocation 50,000

1. Select **Allocate / top up**, choose the company's Cash or Bank source and enter 50,000.
2. An **IAV — Imprest Advance Voucher** is saved as non-posted. It does not yet fund the officer or change accounting reports.
3. Complete the existing Journal Entry approval workflow if enabled in Company books; otherwise a user with **Imprest Post** reviews and posts it.
4. Posting debits the officer Cash ledger 50,000 and credits the company Cash/Bank source 50,000. Company Cash becomes 150,000 and officer Cash becomes 50,000. This transfer is not income or expense.
5. The officer selects **Expense voucher**, chooses an Expense ledger and enters the recipient, amount and date. This creates a **PCV — Petty Cash Payment Voucher**. A non-posted expense reserves its amount immediately. A 10,000 draft leaves 40,000 available, with no ledger effect yet.
6. Posting the PCV debits the Expense ledger and credits officer Cash. Posted balances and financial reports update through the existing double-entry ledger.

## Controls

- Company and officer access is checked on the server. Officers cannot create vouchers against another officer's fund or use general journal, cash/bank payment, invoice, role-edit or other Accounting mutation routes to bypass the restriction.
- The officer cannot allocate advances or post their own vouchers. Managers control funding and posting. Existing paper CEO-signed-copy and Finance Head verification rules continue to apply when selected for general journals or cash payments.
- Source cash/bank needs sufficient posted balance. Pending imprest advances also reserve the source for further advance drafts.
- Pending expense drafts reserve officer funds cumulatively; multiple drafts cannot spend the same money twice. Company-level locking serialises funding, spending, cancellation and posting requests.
- Date/closed-period, expense-hours and posting validations still apply. Backdated expenses must leave subsequent daily fund balances non-negative.
- Retry keys prevent a repeated request creating another voucher. Reusing a key for different details is rejected.
- To correct a draft, withdraw/return its approval if necessary, then **Cancel draft** and create a replacement. Cancelled drafts retain their record, release the reservation, and cannot be posted or approved.
- Posted entries remain immutable. An authorised manager can reverse them using the existing journal reversal action. Reversing an expense restores officer cash; reversing an advance is blocked if spent or reserved funds would be taken away.
- Officer Cash ledgers are protected from manual voucher usage, invoice settlement, retyping and deletion. They remain visible in the chart and financial reports.
- Each company has its own fund per officer. Top-ups use the existing fund. This initial flow uses the company's base currency; foreign-currency imprest allocation and fund closure/hand-over are not included.

## Deployment

Back up the production database, pull this update and run `php artisan migrate --force`, then build the frontend and restart the queue worker. Migration `2026_11_18_140000_create_accounting_imprest.php` creates the fund/movement records and Junior Finance Officer role and adds the new Imprest permissions to applicable protected built-in roles. It does not reset custom-role permissions, assign officers, move cash or post financial entries.

New permissions are **Imprest View, Create, Manage and Post**. Manage permits assigning funds and drafting advances; Create permits drafting expenses; Post permits posting when the configured approval policy allows it. Broader approval roles still require their existing workflow and journal/cash-payment permissions for the approval queue.
