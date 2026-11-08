# Application audit ? 8 November 2026

Scope: Judibas Administration, product catalogue, PMS, Accounting, Canteen, CV Bank, Daymark and Communication. This was an inspection/test run; application business code and existing business records were not changed to address findings.

## Results

- Complete Laravel suite: **348 passed, 1 failed**, 4,167 assertions. The failure is the Accounting approval demo seeder fixture described below.
- TypeScript validation and Vite production build: **passed**.
- **17 browser check groups passed**, including two older checks verified using audit-only copies aligned with the current navigation. The original two scripts remain outdated and are listed below.
- Public/guest smoke run: 19 routes at desktop/mobile widths plus two PMS font requests, **40 checks**. No JavaScript runtime exceptions or horizontal overflow were detected. The guest CV Bank 403 is the navigation inconsistency below, not an access-control leak.
- All four product themes were tested in light/dark mode at 375, 768, 1440 and 1920 pixels: **32 theme/viewport checks**.

## Confirmed findings

### 1. Medium: available products are described as unavailable on landing pages

Reproduction: visit `/products/projects`, `/products/accounts`, `/products/canteen`, `/products/cv-bank`, or `/products/daymark`.

Each has an **Open product** action but its availability section says the product **is coming soon**. The same generic template also labels the product as in development and its FAQ says it cannot yet be used. These messages conflict with the implemented product workspaces.

Source: `resources/js/App.vue`, generic product landing template, approximately lines 121?125. Communication has its own landing template and does not show this issue.

Impact: misleading product availability and confusing navigation; this is not a reproduced accounting or other processing failure.

### 2. Low: guest CV Bank route shows 403 instead of the usual login flow

Reproduction: in a fresh unauthenticated browser, visit `/cv-bank`. It returns HTTP 403 on both desktop and mobile. The other checked workspace entry routes lead to login.

Source: `app/Http/Controllers/CvBankController.php` dashboard invokes `CvBankAccess::authorize` before an unauthenticated login redirect; `app/Services/CvBankAccess.php` intentionally denies missing product access with 403.

Impact: inconsistent guest experience. The authorization guard is working; no CV/application access leak was observed. A guest redirect can be separated from the required 403 for authenticated users without CV Bank permission.

### 3. Low / test data: Accounting approval demo seeder test fails on existing demo emails

Failing test: `AccountingApprovalTest::test_demo_credentials_are_company_scoped_and_seeder_does_not_duplicate_entries`.

The seeder checks for `Atlas Accounts (TEST ONLY)` and, when that company is absent, refuses to create sample accounts if a requested demo email already exists. It fails safely with `A requested demo email already exists. Existing accounts were not changed.`

Sources: `database/seeders/AccountingApprovalDemoSeeder.php`, approximately lines 37?42; `tests/Feature/AccountingApprovalTest.php`, approximately line 196.

Impact: the complete test suite is not fully green and rerunning that demo seeder is blocked by fixture state. The failing operation is sample-data creation, not a reproduced ledger calculation/posting error. Existing accounts were not altered to force this test to pass.

## QA maintenance and performance observations

- `scripts/check-pms-project-grid.mjs` assumes the previous PMS root URL/portal metadata. After canonical `/pms/all` reload, its mocked marketing HTML lacks the workspace metadata. An audit-only copy accepting the canonical URL and supplying the same metadata as the real workspace passed project grids, inline departments, board/sprint navigation, refresh, employee controls, race/error handling and mobile layout. No PMS business-code fix was needed for this test failure.
- `scripts/check-accounting-all-companies.mjs` still expects the Company header combobox that was deliberately removed. An audit-only copy using the current company chooser and `/accounting/all` passed combined chart/report views, company contributions, read-only controls and restricted-user company selection. No removed dropdown was restored.
- Vite reports a main JavaScript chunk of approximately **502 kB minified / 154 kB gzip**. This is an optimization opportunity, not a failed build. Future code splitting can help as products grow.
- Vite also warns about two absolute PMS font paths. Both corresponding files exist and returned HTTP 200 during the smoke test; these warnings did not reproduce a missing-font failure.

## Browser coverage

| Area | Verified behavior |
| --- | --- |
| Administration | Company creation/rename/validation through isolated mocks, search/pagination, ten sections, responsive light/dark layouts, compact dialogs |
| Products | Four themes, exclusive accordion, keyboard/search behavior, settings save/reload |
| PMS | Scoped company headers/API requests, independent tabs, URLs and exports, project grids, departments, sprint navigation, refresh and employee actions |
| Accounting | Chart/search/tree, journal and invoice posting UI, debit/credit behavior, unsaved changes, partial payment, exports/loading, single vouchers, signed-copy approvals, permissions, combined company views and company chooser |
| Canteen | Workspace/company URL rendering in browser; backend suite covers company access, order/benefit/stock/report and other automated Canteen cases |
| CV Bank | Company isolation, job create/edit/location fields, departments, career links/settings, HR controls, public details/application flow, six career themes, dark/mobile layout |
| Daymark | Employee/management dashboards and filters, report states, editor lists, long-report scrolling and feedback controls |
| Communication | Normal/protected message UI, no plaintext key transmission in browser test traffic, RSA key delivery, one-time popup, recovery/redelivery, versioned future-key reset, old history/key separation and reviewer masking |

Browser mutation checks use isolated intercepted API fixtures, not live company/report/voucher changes. Browser cryptographic tests use actual Web Crypto primitives. Server-side authorization, scoping, calculations and transitions are exercised separately by the Laravel suite.

## Limits

This audit does not guarantee zero defects outside its coverage. Physical printers, real outbound email delivery, live calls between separate real devices, production load/concurrency and a complete external security/penetration review were not verified. The real multi-user Reverb/Pusher service was not reconfigured or end-to-end load-tested in this audit.

The local port 8000 server stopped during the first browser run. It was restarted for testing and connection-refused checks were rerun. That interruption was treated as an environment problem, not counted as a product bug.

Detailed machine logs/screenshots remain under `.preview/app-audit-*.json` and related audit artifacts. No fixes to the findings above were applied in this audit.
