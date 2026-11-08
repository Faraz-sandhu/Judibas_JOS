# All companies Accounting view

Platform Super Admin and platform administrators can select All companies. Company-assigned finance/manager roles keep their existing boundaries; direct combined API requests are rejected.

Fresh Accounting entry without a company URL opens a required company-selection dialog. Selecting a company or All companies opens that scope. Explicit company/record links and refreshes preserve their selected scope.

All-companies mode presents combined totals plus a shared table, not individual company reports. Overview, chart, trial balance, Profit and Loss, Balance Sheet, ledger, receivables/payables, invoices, vouchers, contacts, audit and approvals are available. Company books use one comparison table because currency, start dates and closing locks belong to each company. Global roles retain their existing page.

Totals cover all matching companies and source pages, independently of the displayed 20-row page. Financial calculations use integer minor units and the existing posted-only reporting rules in a repeatable-read transaction. Trial balance derives closing debit/credit from combined matching-account balances. Different currencies never mix; no currency conversion or intercompany elimination is performed. This is combined management reporting, not an eliminated statutory group consolidation.

Accounts combine only when code, normalized name, root type, account type, group/ledger status and their complete parent hierarchy match. Similar codes with different meanings remain separate. Expand an account row for company contributions and links to source accounts/ledgers. Unconfigured companies are disclosed, and cash-basis exclusions are listed.

Document list totals separately identify posted, non-posted and reversed document face values; these are not represented as ledger balances. Financial mutations still require one valid company ID and the existing permissions.

The existing Accounting shell remains visible while the company selector opens. Combined Chart of Accounts uses the original hierarchy, expand/collapse controls and right-hand account details panel. Overview retains financial cards. Combined Profit and Loss and Balance Sheet retain the original statement layout with a source-details panel; combined scope changes the amounts, not the navigation structure.

The Accounting product information page at `/products/accounts` keeps the shared Judibas hero, preview, feature sections and site rail. Its Open Accounting button opens the workspace selector at `/accounting`. The selector is a compact 410px searchable list with a scrollable company list. Selecting a row opens that company immediately; no separate Continue action is needed. Regular users receive only their assigned companies from the backend.
