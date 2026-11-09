# Judibas Focus To Do

Implemented from the supplied **Focus To-Do User Documentation, desktop version 7.2.2 (26 pages)**, using the existing Judibas design and shared account model.

## Open and grant access

- Open `/focus-to-do`, or choose **Focus To Do** in Products / the shared application menu.
- Super Admin and platform administrators can open it. For employees, grant **Focus To Do** through the existing Administration user or invitation form.
- Tasks, notes, preferences, sessions, notifications and forest data belong to the signed-in person. Super Admin has a separate personal workspace; this is not employee monitoring.
- Company assignments govern Groups and Ranking visibility. Personal planning does not require a company. Users without an assigned company can still use tasks, timers and reports.

## Documentation coverage

| Documentation area | Judibas implementation |
| --- | --- |
| Home / task lists | Today, inbox Tasks, optional smart lists, task search, counts, estimates, summary cards and paginated task rows |
| Tasks | Title, notes, priority, tags, due date, reminders, estimated sessions, repeat rules, subtasks, complete/reopen and deletion |
| Projects | Project and folder creation/editing/deletion; project filtering; deletion retains tasks and recorded focus history |
| Focus screen | Pomodoro countdown, stopwatch, pause/resume, finish, reset, selected task, full screen, mini timer and recent session timeline |
| Breaks | Configurable short/long breaks, long-break interval, automatic starts and disable-breaks preference |
| Sound | Browser-generated alarms and ambient/ticking sounds, mute and volume settings; no external audio requests |
| Reports | Today/week/lifetime summaries, date range, daily/weekly/monthly/yearly chart grouping, project distribution, hourly heatmap, goal calendar and paginated session history |
| Manual records | Add and edit recorded focus time; clearly labelled, included in personal reports but excluded from sunlight and ranking |
| Groups | Company-scoped discovery/search, creation, join/leave, owner deletion, members and focus totals |
| Forest | Collectable daily/return-visit/session sunlight, activity history and growth levels |
| Ranking | Active permitted company users, last 30 days/overall, personal position; no task contents exposed |
| Notifications | In-product inbox, mark read, task reminder links and optional browser alerts |
| Settings | Timer, general preferences, smart lists, appearance and account/access information; explicit Focus-only data deletion |

## Intentional web / Judibas adaptations

- Shared Judibas sign-in, invitations and account administration replace the source app's independent registration/account system.
- Premium features are included with product access. No foreign vendor billing, app download, rating or subscription screens are reproduced.
- Appearance inherits Judibas Light/Dark/System. Focus backgrounds provide subtle product-specific alternatives.
- Groups and rankings are restricted to assigned companies instead of a public worldwide directory. Only aggregate focus time and permitted names are shown.
- Generated browser sounds approximate the documented sound categories; the original vendor's recordings/background artwork are not copied.
- The forest uses a transparent rule: daily visit 30g, consecutive-day visit 6g, completed focus session one gram per full minute. Each 600g advances a level. These are Judibas rules, not a claim to reproduce undisclosed vendor scoring.
- Browser sound/fullscreen/notifications require browser permission or user interaction. Closing the workspace stops sound; persistent operating-system notifications with a closed browser would require a separate push/service-worker integration.
- An absent browser does not invent a chain of completed Pomodoros: only the elapsed active phase is reconciled, and any automatic next phase begins when the server processes it.
- Recent activity in the workspace is capped; the Reports record table provides server-side pagination over the selected date range.

## Runtime and integrity

- PHP scheduler: run the normal Laravel scheduler (`php artisan schedule:work` locally; `schedule:run` every minute in production). The named `focus-reminders` event handles due reminders and unattended timer reconciliation.
- Existing Reverb or Pusher configuration is reused. Own private channels are authorised through `/focus-to-do/api/realtime/auth`; no Communication permission is required.
- No record polling interval is used. A one-second browser interval draws the timer; mutations, deadline sync, visibility/reconnect and private events reload authoritative data.
- A database lock per owner and a unique active-owner constraint prevent concurrent active timers. Elapsed segments are accumulated on the server and capped to the session duration (24-hour maximum stopwatch).
- Timestamps include their UTC offset. Reports divide segments across local date/hour boundaries using the configured IANA timezone.
- Recurrence creation, reminder delivery, daily rewards and completed-session rewards are idempotent.
- New frontend code is isolated in `resources/js/modules/focus-to-do/`; backend tables use `focus_` names and do not change accounting ledgers or other product data.

## Verification

- `php artisan test --filter=FocusProductTest`: access/ownership, recurring completion/reopen, timer pause/resume/refresh, stale actions, manual records, midnight splitting, project folders, company privacy, reminder idempotency and Focus-only deletion.
- `node scripts/check-focus-workspace.mjs`: fixture-isolated browser workflow checks, tasks/details/subtasks, timer and mini timer, reports, social screens, settings, pagination, and responsive widths. It does not mutate live employee data.
- `npm run build`: TypeScript and production bundle.
