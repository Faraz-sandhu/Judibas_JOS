# Linked company orders

This flow is for two companies already present in Judibas Accounting. It does not replace external supplier purchase processing.

1. Open the buyer company's **Purchase orders** (or the seller company's **Sales-side orders**).
2. Select **Create linked PO**. Choose the seller/service provider and buyer/customer explicitly. The buyer pays the seller.
3. Enter descriptions, quantities, quoted and agreed rates, order/due dates and terms. Example: quoted PKR 200,000, agreed PKR 180,000.
4. Save. One shared intercompany agreement produces a `PO-…` view in the buyer and `SO-…` view in the seller, linked by `IC-…`.
5. Confirm the agreement. Create both invoice drafts with seller receivable/income and buyer payable/expense-or-asset account mappings.
6. Review applicable taxes in each draft. Approve/post each invoice through the existing Accounting workflow; payments and reversals remain separate per company.

The buyer has the seller as a supplier; the seller has the buyer as a customer. These use explicit company-to-party mappings. On first use, a dedicated mapped contact is created; unrelated same-name contacts are never silently merged. Customers/Suppliers include an Orders link.

Orders and invoice drafts have no ledger effect. Posted invoices update the existing company ledgers and reports. “Invoiced” means drafts have been generated, not that both companies have posted or settled them. The detail screen shows the current company's invoice state.

## Controls and limits

- Creating/confirming/cancelling/converting the pair requires company access and the relevant order permission on both sides. Read-only access to one side does not grant mutation of the other.
- New permission groups: Purchase orders and Sales-side orders. Built-in role presets are updated by migration; custom roles require explicit grants.
- Both company books must exist and use the same base currency. This initial flow creates pre-tax drafts; it does not silently decide statutory tax treatment or exchange rates.
- Duplicate requests are idempotent; both invoice drafts are created in one transaction. Invalid second-side mappings roll back the first side as well.
- Order lines/parties cannot be silently changed in a generated invoice. Account and tax configuration can be reviewed under existing invoice rules.
- Once invoice drafts exist, order cancellation is blocked. Manage invoice correction/reversal through the existing financial lifecycle. Invoices are not automatically posted or paid together.
- All-companies mode asks for a specific company before working with orders.
- No automatic intercompany elimination or group profit calculation is introduced.

Validation: `AccountingOrdersTest` covers pairing, company contacts, duplicate prevention, amounts, stale versions, access isolation, atomic invoice conversion and both existing invoice posting paths. `scripts/check-accounting-orders.mjs` exercises the UI with isolated fixtures.
