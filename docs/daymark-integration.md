# Daymark in Judibas

Daymark is available at `/daymark` using the shared Judibas login, product catalogue, application switcher and light/dark appearance. Its Laravel domain logic was ported from the local Daymark application; its interface is Vue 3 and uses Judibas layout, buttons, dialogs, tables and notifications.

## Feature mapping

| Original Daymark capability | Judibas location |
| --- | --- |
| Login, logout and employee identities | Shared Judibas account and header |
| Account password change | Daymark → My account (changes shared password) |
| Company creation and management | Administration → Companies; linked from Daymark → Companies |
| Employee creation and product access | Administration → Users → Daymark reporting access |
| Invitation acceptance, expiry, revocation and email/manual link | Administration / Invitations (one shared invitation for all products) |
| Reporting company, department, designation, role and multiple managers | Administration → Users and Daymark → People |
| Company admin, CEO, MD, HR, PM, OM and employee roles | Daymark roles with original data scopes |
| Custom company roles with restricted base permissions | Daymark → Roles & access |
| Company departments, reuse and guarded deletion/replacement | Daymark → Departments |
| Dashboard, weekly reporting chart, counts and recent reports | Daymark → Overview |
| Daily reports, rich text, hours and prior-date lookup | Write report / Daily reports |
| Private drafts and one report per author per date | Enforced by server and database |
| Submitted → Reviewed / Changes requested → Resubmitted | Daily report review dialog |
| No self review, private drafts hidden from admins, reviewed-report locks | Enforced by API and tested |
| Follow-up/action items with reviewer feedback | Overview → Follow-up needed |
| Company/person/department/status/date/month filters | Daily reports and Monthly overview |
| Monthly people summaries and report list with pagination | Monthly overview tabs |
| CSV and monthly Excel | Report export buttons |
| Daily company/department/person grouped PDF and Excel | Day-end report |
| Activity history | Daymark → Activity log |
| Shared email settings | Administration → Settings; linked from Daymark Settings |

Employees see their own reports. PM/OM see themselves and explicitly assigned people in their reporting company. Company admins, CEO, MD and HR see their reporting company. Judibas administrators oversee every company but cannot author personal reports. Custom role permissions never expand base-role scope. Removing product access or deactivating an account blocks Daymark requests and retains historical reports.

Each person has one Daymark reporting company, independently of their memberships in other products. A person with report history cannot silently move their historical reports into another company. Delete protection asks administrators to deactivate accounts with history instead.

## Setup

1. Run `php artisan migrate` and `npm run build` when deploying.
2. Super Admin opens Daymark → Departments and creates reporting departments for existing Judibas companies.
3. In Administration → Users or Invitations, select Daymark, reporting company, reporting role, department and designation. Assign PM/OM managers as needed. Daymark company roles do not grant access to central administration.
4. Employees open Daymark from the shared application switcher.

The original Daymark database and files are unchanged. Existing Daymark accounts, sample reports and passwords are **not imported** into Judibas. This is a functionality integration using existing Judibas identities, not a production data migration.

## Verification

`php artisan test --filter=DaymarkProductTest` checks shared provisioning, permissions, draft privacy, company and assignment isolation, report validation and review locks, HTML safety, invitation acceptance, department replacement, exports and access revocation. `node scripts/check-daymark.mjs` checks the Vue screens with isolated API fixtures and no live report changes. Full project tests cover regressions in other products.


## Local demo dataset

Run `php artisan db:seed --class=DaymarkDemoSeeder` in a local environment. The seeder creates two clearly labelled demo companies, 14 accounts, six departments, reporting-manager assignments, and 100 reports across the preceding 14 days (weekdays only). Existing demo reports are preserved when rerunning the seeder. No emails or invitations are sent.

All newly created demo accounts use password `DaymarkDemo2026!`. Example logins:

| Purpose | Email |
| --- | --- |
| Employee (Alpha) | daymark.employee1.alpha@example.test |
| Second employee (Alpha) | daymark.employee2.alpha@example.test |
| Project Manager (Alpha) | daymark.pm.alpha@example.test |
| Operations Manager (Alpha) | daymark.om.alpha@example.test |
| HR (Alpha) | daymark.hr.alpha@example.test |
| Company reporting admin (Alpha) | daymark.admin.alpha@example.test |
| Employee in another company | daymark.employee1.beta@example.test |
| Inactive account (login must fail) | daymark.inactive.alpha@example.test |

Replace `alpha` with `beta` for the second company's equivalent accounts. Demo company admins review their company's reports; they are not Judibas administrators. Use the existing Super Admin account to test shared company, department, role, employee and invitation administration.

Daymark product navigation now contains reporting, review, activity and account screens. Setup is centralised under Administration. Old product management endpoints also require Judibas administrator access; a reporting role alone cannot use them.


## Realtime report updates

Daymark uses the shared Administration / Settings / Realtime configuration (Laravel Reverb or Pusher), with authenticated private channels for each viewer. Keep Reverb running when that provider is selected. No periodic polling is used. The Daymark header displays the connection state.

Saving or submitting a report and approving/requesting changes broadcasts a small invalidation event after the database transaction succeeds. The receiving browser reloads through its permission-filtered API. Draft changes are sent only to their author. Report content is not broadcast. Unsaved editor text is preserved during incoming updates; reviewed status and feedback are refreshed separately.

PM/OM access remains explicit reporting assignments in the same company, plus self. The Alpha demo PM and OM are assigned to both Alpha demo employees. They do not see Beta reports. Company Admin/CEO/MD/HR roles have company-wide reporting visibility; Judibas administrators have platform-wide visibility.

`php artisan test --filter=DaymarkRealtimeTest` verifies event audience, draft privacy, private-channel authorization, revocation, and Reverb/Pusher signing. `node scripts/check-daymark-live.mjs` verifies real employee ? PM/company-admin delivery and the review/resubmission/approval cycle without page refreshes. The live script uses the local demo accounts and removes its temporary report afterward.
