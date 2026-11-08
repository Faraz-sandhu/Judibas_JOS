# Accounting activity notifications

The notification bell appears in the Accounting header for roles with **Activity notifications → View**, and in the Administration header for Super Admin/Judibas Admin. Built-in Accounting Manager and Senior Finance Manager roles include this permission. Finance Officer, Accountant, Viewer and CEO templates do not receive the administrative activity feed by default; custom roles can grant it explicitly.

The feed records audited Accounting actions: draft creation/updates, posting, reversals, approval decisions, signed-copy uploads/access, chart/contact changes, books/role changes, printing and report exports. It displays the actor, action, document/reference, company and time. It starts collecting new activity after installation; existing audit history is not backfilled. A viewer's own actions are excluded from their notification feed.

Notifications are persisted with their source audit record in the same transaction. Rolled-back actions produce neither a retained notification nor a broadcast. Publishing occurs after commit with `ShouldBroadcastNow`, so no queue worker or polling interval is required. A broadcast failure does not undo a successful financial transaction; the stored feed remains available on reopening/reconnection.

The shared PlatformSettings Reverb/Pusher configuration is used. Private administrator/company channels carry only notification/company identifiers. The client reloads the feed through a permission- and company-scoped endpoint when it receives an event and when it subscribes again. Session-only Super Admin authentication uses the dedicated Accounting realtime auth endpoint; it does not depend on an authenticated employee user object.

Global administrators can see all companies. Accounting managers/custom authorised roles see only currently assigned companies. The Accounting header feed follows the selected company; Administration shows the global administrator feed. Role/product/company removal is enforced on API reads and new channel authorizations. Read state is separate per viewer. Mark all read applies only through the latest visible notification identifier, preserving later arrivals as unread. Older entries can be loaded inside the dropdown.

Endpoints:
- GET `/accounting/api/notifications`
- POST `/accounting/api/notifications/read`
- GET `/accounting/api/notifications/realtime/config`
- POST `/accounting/api/notifications/realtime/auth`

Verification includes persisted notifications from Finance activity, actor identity, independent read state, company isolation, permission revocation, session-only administrator and manager private-channel authentication, and rollback behaviour. `scripts/check-accounting-notifications.mjs` exercises the actual Pusher/Echo client against a mocked WebSocket server and mocked feed APIs to verify event-driven badge updates, dropdown content/read actions, dismissal, mobile bounds and permission hiding. Feature tests exercise application endpoints against rolled-back database fixtures.

Notification links resolve the company and exact current record: journals/vouchers, invoices, contacts, chart accounts, roles and payment approvals. Viewer permissions are checked before issuing links and again by the destination API. Settings and general activity open their related workspace. Expense window errors display configured hours in 12-hour AM/PM format.
