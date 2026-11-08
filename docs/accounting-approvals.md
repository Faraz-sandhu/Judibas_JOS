# Accounting roles and approvals

## Administration and access

Create custom roles in Accounting → Roles and permissions, then assign the role when creating/editing an employee or sending an invitation in Administration. Accounting access and at least one assigned company are required. A role applies across that employee's assigned companies, matching the shared product-access model. Role definitions are product-wide, not per-company.

Built-in templates are Viewer, Accountant, Accounting Manager, Finance Officer, Senior Finance Manager and Company CEO. Copy a template to customise it. Built-in roles cannot be modified. Inactive roles cannot be assigned or used for actions. Revoking Accounting access or company membership blocks subsequent requests.

Permissions cover chart administration, customers, suppliers, sales/purchase invoices, payments, journals, cash vouchers, each report, exports, cash-basis allocations, company books, audit history, role administration and individual workflow stages. Both navigation and server endpoints enforce permissions. Having access to a page does not grant its create, update, post, reverse or export actions. Account metadata remains available for authorised document pickers; balances require chart/report access. Workflow View grants access to the company's approval documents, including their financial details.

Only trusted product administrators should receive Roles Manage: custom roles are shared across companies. Role editors cannot grant permissions they lack, edit their own assigned role, overwrite stale changes or alter protected templates. Super Admin/Judibas Admin retain all product permissions.

## Online invoice and payment posting sequence

Company books enable approval by default:

Saved draft → Finance review → CEO approval → Finance verification → Finance Manager final approval and posting → Ledger

The online sequence below applies to sales/purchase invoices and invoice payment requests. Journal vouchers (including opening entries and CPV/CRV/BPV/BRV/EV/IV) use the paper-signature process described below. Contacts, chart maintenance, report exports and cash-basis reporting annotations use their own permissions rather than this financial-document workflow. Reversals retain their separate explicit Reverse permission, audit history and existing period checks.

1. Prepare and save a balanced draft. It has no ledger effect.
2. Submit the saved version to Finance with an explanatory note.
3. Another Finance reviewer checks it and forwards it to the CEO.
4. The CEO approves and forwards it for Finance verification.
5. Finance verifies and forwards it to the Finance Manager.
6. An authorised Finance Manager gives final approval and posts. Both Finalize and the document's Post permission are required.

Use Accounting → Approvals for the company queue, stage filters, document review, decisions and history. The same workflow panel appears on saved documents. Each decision records the person, time, stage and explanatory note.

The preparer cannot perform the initial Finance review, CEO approval or final posting of their own preparation. The CEO approver cannot also perform final posting. Finance review and verification can be performed by the same Finance reviewer. Super Admin/Judibas Admin can act at every stage for administration, but must still progress through every stage with recorded decisions; direct posting remains blocked while approval is enabled.

## Corrections and safeguards

- Submitted documents cannot be edited. Return or withdraw them first, save corrections, and submit the latest version starting again at Finance review.
- Return/withdraw of a payment request cancels that request; prepare a new corrected payment request.
- Pending payment requests do not reduce receivables/payables or create ledger entries. Pending amounts reserve invoice settlement capacity to prevent over-allocation.
- Amount, company, version, document status, closed period and existing posting checks are enforced again at final posting. Cash payment vouchers recheck available funds.
- Direct posting endpoints cannot bypass the workflow, even for an administrator. The server creates the final-posting context internally.
- Stale or repeated approval decisions cannot post twice. Company locking serialises concurrent financial actions.
- Authorised users with Books Manage and Workflow Configure may disable approval for a company only after active approvals have been completed or withdrawn. Existing history remains available.
- Existing posted documents are not reposted or retrospectively submitted. Drafts follow the company's current policy.

## Verification

Feature tests exercise four-stage posting, self-approval restrictions, direct posting rejection, stale decisions, return/edit/resubmission, company scope, custom role enforcement/deactivation, protected role templates, pending payment reservations, final payment posting, closed periods, and custom-role user/invitation provisioning.

`scripts/check-accounting-approvals.mjs` checks the browser interface using mocked API fixtures; feature tests exercise real application endpoints inside rolled-back database transactions. Existing chart and ledger browser regression scripts are also retained.


## Paper approval for non-posted vouchers - 4 November 2026

Save a non-posted voucher ? print it ? CEO checks and stamps/signs the physical paper ? Finance photographs/scans the signed paper ? attach it to the saved voucher ? an independent Finance Head verifies the copy and posts.

The voucher detail page offers **Print voucher for CEO signature** and an upload section. A JPG, PNG, WebP or PDF up to 10 MB is accepted with an explanatory note. Uploading from a saved draft creates its paper approval automatically; existing pending paper approvals accept the copy at their Awaiting CEO-signed copy stage. CEO online approval is not required for this voucher process. The CEO's actual stamp/signature is checked manually by the Finance Head, not automatically recognised by the software.

Uploads are private local files behind company and workflow access checks. Signed copies record the uploader, time, original filename, content hash, saved document version and submission cycle. The Finance Head can view or download the evidence and must explicitly confirm the stamp/signature, voucher number/version and amounts before posting. The preparer or uploader cannot be final verifier; the existing global administrator exception for actor separation remains, but signed evidence and verification confirmation are still mandatory.

Return/withdraw permits correction and requires a new signed copy for the next submission, even if amounts were not changed. Earlier copies remain in history. No replacement or deletion endpoint is provided. Missing evidence, missing files, stale versions, closed periods, unavailable funds and unauthorised posting block the operation. Posted history is retained. The migration moves existing pending journal approvals to Awaiting CEO-signed copy and records the policy change.

Non-posted labels appear in voucher lists/detail panels and printed documents. Prints include the saved version and CEO stamp/signature space. The whole voucher remains unposted until Finance Head approval; individual lines are not posted separately.


## Document-wise approval scope

Company books now provides one selection per document type: general journals, opening entries, CPV, CRV, BPV, BRV, EV, IV, sales invoices, purchase invoices, customer payment requests and supplier payment requests. The master approval switch and selected types together determine where approval applies. Existing companies preserve their previous all-types policy until explicitly changed.

Only selected types require the relevant paper/online approval process. Unselected types retain ordinary saved drafts and permission-controlled manual posting; changing scope does not auto-post anything. Configuration changes require Workflow Configure in addition to Books Manage, use the books version check, and cannot bypass an active approval: finish or withdraw the affected document first. Returned/withdrawn approvals remain in history when the type is subsequently unticked.

Financial reports are independent of this configuration: every unposted draft remains excluded from General Ledger, Trial Balance, accrual/cash Profit and Loss, Balance Sheet and ledger-derived balances. Uploading the signed copy or advancing review stages does not post the document. Only final/manual posting changes the financial reports. Tests cover exclusion before submission, during paper approval, and after signed-copy upload, then inclusion after final posting.
