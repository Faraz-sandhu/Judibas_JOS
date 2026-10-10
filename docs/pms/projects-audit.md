# Projects module audit — 2026-10-10

Reference sources: PMS-PROJECT Blade project views, controllers, models and supplied live PMS. Live records were inspected without submitting changes.

## Source backend review

Compared ProjectController, SprintController, TaskController, TaskIssueController, TaskHandoffController, ProjectChatController, SubmissionController, SubtaskController and TimerLogController against the source. Project and sprint business logic is retained. Adaptations use PMS namespaces/table names, Judibas permissions/authentication, Vue JSON responses and workspace URLs. Existing handoff, timers, estimates, approval restrictions, notification services and task/subtask visibility remain.

## Corrections

- A direct board refresh waits for the requested board instead of briefly rendering the project list.
- Workflow and sprint selection persist in the URL and survive refresh.
- Creating a sprint opens that sprint, matching the original source.
- Optional empty sprint dates become null, matching the original request payload.
- Deleting a sprint restores the backlog in the UI and URL; backend retains its tasks.
- Project edit dates are normalized for browser date inputs.
- Direct card Assign/Edit/Handoff actions open their own dialog without a task-details dialog behind them.
- Task assignment uses the source immediate add/remove/clear picker with search and email invitation lookup.
- Workload includes user avatars, current/project totals, task status badges and its empty state.
- Project submissions use the source fullscreen table layout.
- Project discussion uses the source Comments dialog size and positioning.
- Original estimate shows hours/minutes, usage percentage, warning colors and progress.
- Task comments use source avatars and own/other bubbles; delete visibility respects authorship.
- Card priority colors and fallback avatars are restored.
- Attachment pickers include the original allowed extensions and limits text.

## Verified coverage

Browser inspection opens project create, edit, preview, assignment, discussion, submission table, sprint create, full issue create, workload, task details, task edit, assignment and handoff dialogs. Board refresh and automatic navigation to a newly created sprint are checked. Destructive actions are verified through transaction-based backend tests rather than changing reference records.

PMS test suite: 23 tests and 276 assertions passed, including sprint create/edit/complete/delete, sprint filtering, backlog preservation, cross-project sprint rejection, comments and attachment upload/delete/type validation. Existing tests cover project create/edit/status/approval/delete, project discussion replies/seen state/access, assignment/removal, task/subtask updates, timers, handoffs, invitations and scheduled cleanup.

The supplied live project has no sprints. Its empty/backlog board, sprint form, issue form, workload panel, task details, task edit and immediate assignee picker were inspected. Active/planned/completed sprint states are checked with local fixtures and backend tests. This coverage does not establish that every possible permission/data state is free of defects or that every screen is pixel-identical.
