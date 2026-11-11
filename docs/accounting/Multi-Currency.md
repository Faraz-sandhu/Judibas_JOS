# Multi-currency testing guide

Existing work was pushed to `origin/main` at `ab4de88` before this feature. Multi-currency is a separate change for local testing.

## Where to use it

1. Accounting → select a company → **Currencies & rates**. Finance users with Company books management permission can save a dated rate. The direction is **base-currency units for one foreign unit**: e.g. USD 1 = PKR 282. Rates are manually verified, not live market quotes.
2. **Chart of accounts** → create/edit an unused cash/bank ledger → choose its account currency. Leave ordinary PKR accounts as Base currency. Currency is fixed after use. Foreign bank details show original-unit and base carrying balances; group totals stay in the base currency.
3. **Sales/Purchase invoices** → currency and exchange rate. Quantity and unit price are entered in the transaction currency. Saved totals show original and base amounts. Posting writes base-currency journal values, retaining original-unit metadata.
4. **Invoice → Receive/Make payment** → amount is in the invoice currency. Enter the payment-date rate and choose the appropriate Income/Expense exchange account if needed. A base bank uses converted cash proceeds; a foreign bank receipt retains original units. A foreign-bank supplier payment allocates its historical carrying value, so consuming all original units also consumes the remaining base balance. The net realised gain/loss is posted separately.
5. **Journal entries / Single vouchers / Cash vouchers** → use the currency section. Both original and converted totals must balance. For manual foreign bank/cash outflows, the entered rate must produce the allocated carrying value; otherwise the server rejects the draft/post rather than leaving an invalid base balance. Invoice payments handle carrying-value allocation automatically.
6. **General Ledger** and CSV include original currency, original debit/credit and saved rate. Trial Balance, Profit & Loss, Balance Sheet and overview totals remain in the base currency. Aging adds original outstanding amounts; its totals remain in the base currency. Saved invoices and journal/voucher prints include currency information.

## Suggested example (sample rates only)

- Create USD 1,000 sales invoice at 280 → PKR 280,000 receivable.
- Receive USD 400 into a PKR bank at 282 → bank PKR 112,800; receivable reduction PKR 112,000; exchange gain PKR 800.
- Receive remaining USD 600 at 281 → bank PKR 168,600; receivable reduction PKR 168,000; gain PKR 600. Both original and base invoice outstanding become zero.
- Reverse a payment: the exact original base and foreign allocations are reversed and its outstanding amount returns.

## Controls and current boundaries

- Supported two-decimal currencies: PKR, USD, AED, GBP, EUR, SAR, CAD, AUD. Exchange rates have up to six decimal places. Server arithmetic uses integer minor units and decimal proportional allocation, not floating-point money multiplication.
- Partial settlement uses the invoice's historical carrying value, with the final payment consuming the exact remaining base amount. Requests remain idempotent; company locking serialises financial updates.
- Rates are suggestions from the saved schedule; every transaction keeps its own verified rate. Rate edits do not rewrite saved/posted documents. Books cannot change their base currency after financial documents or rate records exist.
- Existing approval controls, closed dates, immutability, reversals, product/company permissions and notification flow remain in use. Pending payments reserve original invoice units until posted.
- Invoice net is the sum of individually rounded base line amounts; tax is converted separately. Saved base total is their sum. Extremely small amounts that convert to zero are rejected.
- Payments currently settle in the **invoice currency**. Paying a USD invoice directly from an AED account is rejected; cross-currency bank conversions need a separate workflow. Separate bank fees are not silently deducted from the invoice.
- **Automatic period-end revaluation is not included.** Reports retain historical carrying values until reviewed adjustments are made. Do not treat this feature as a completed FX revaluation or statutory-reporting implementation. No live rate provider or automatic tax conversion rules are introduced.
- Linked intercompany PO conversions remain base-currency only. Their existing same-currency restriction is intentional.

## Revert

Keep the feature's code separate from `ab4de88`. Before actual foreign transactions are used, it can be reverted independently. Once foreign postings exist, reverting code/schema must preserve those records and their original/base amounts; never drop the currency columns or reinterpret a foreign invoice as a base-only invoice. A data-aware rollback is required then.

Validation: `AccountingCurrencyTest` covers foreign invoicing, partial/final settlements, gains/losses, reversals, duplicate requests, foreign bank carrying balances, rate validation and pending-payment reservation. `scripts/check-accounting-currencies.mjs` checks the built UI with isolated fixtures (no real financial postings).
