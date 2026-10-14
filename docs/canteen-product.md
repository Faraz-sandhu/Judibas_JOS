# Canteen product

## Shared identity and access

Create or invite employees only in **Judibas Administration → People & access**. Select Communication, PMS and/or Canteen and the employee's companies. A single account can open all three products. Canteen roles are Employee, Canteen Admin, Kitchen and Cashier; the selected role applies to the selected companies. Super Admin and Judibas Admin manage all companies. Benefit eligibility is separate from the role and from PMS roles/departments.

Removing Canteen product access or company membership blocks subsequent requests without deleting historical orders or benefits. Canteen does not create another login or invitation system.

## Initial company setup

1. Open `/canteen`, select a shared company and initialize levels/categories, or create them manually.
2. Initial levels use company shares of 50%, 60%, 70% and 80%; names, percentages and active states are editable.
3. Assign benefit levels to centrally created employees under Employee benefits.
4. Add items, prices, availability times, eligible levels, daily/weekly/monthly item allowances, optional percentage overrides and stock quantities.
5. Configure company Settings: timezone, within-allowance charging, excess policy and ordering enabled/paused.

Ordering remains paused until a policy is deliberately chosen. Within allowance can be free or use the contribution split. Excess can use the contribution split, charge full price or block ordering. Unchecked benefit levels cannot order an item. Blank item allowance means unlimited. Stock tracking supports packaged units and prepared portions.

## Orders and money

Employees order from their own authenticated Judibas account. Quotes are calculated on the server and signed. Confirmation rechecks current eligibility, policy, time schedule, consumption, price and stock; a changed quote requires another review. Profile and item row locks serialize consumption/stock changes. Request UUIDs make identical retries idempotent. Prices, employee/level/category names and contributions are snapshotted onto orders.

Confirmed → Preparing → Ready → Delivered is enforced. Kitchen can progress orders and print kitchen receipts, but cannot view financial receipts or collect payments. Cashier can record cash received and cancel only unpaid, unfulfilled orders. Cancellation restores tracked stock and releases the consumption quantity. Paid-order refunds are not enabled.

Orders and policies are company-scoped. Employees see their own orders; kitchen/cashier/admin see orders within authorized companies. Administrative changes, confirmations, payment/status changes, cancellations, report exports and receipt openings are audited.

## Reporting and inventory

Reports support company/date, employee, level, item and category filters, with grouping by item, category, employee, level and day. CSV, Excel and PDF exports are available. Stock movements preserve opening balances, adjustments, order deductions and cancellation returns; dashboard flags low stock.

## Printing and remaining hardware scope

Two distinct printable receipt formats are implemented using the browser print dialog. Automatic unattended routing to two physical printers requires a local printing connector and the printer models/connection details; it is not implemented or claimed by this release. Receipt opening/reprinting requests are audited, not proof of physical print completion.

The current identification method is the existing authenticated Judibas account. Shared kiosk Employee ID/PIN, QR/RFID/NFC, payroll deductions, wallets, branches, automatic performance assessment and printer-job queues are separate future integrations. Category-wide pooled allowances and inventory purchasing/costing are not implemented; current allowance rules are per item and benefit level.
