# PMS reference alignment

Reference: the original PMS-PROJECT Blade templates and read-only inspection of the supplied live PMS on 2026-10-09.

## Implemented

- Shared dialogs: source-sized 560 px standard, 580 px project assignment, 800 px large and 1140 px project forms; source top/center positioning, close controls, two-column fields, footer spacing, scrolling and keyboard focus.
- Project assignment: compact searchable employee picker with role badges; existing project members remain removable from table avatars.
- Project and department sprint boards: shared source toolbar, sprint goal, metrics, quick creation, full issue form, cards, assignees, action menus and drag/drop positioning.
- Sprint forms: name, goal, dates and Start immediately; existing edit/delete and sprint state actions retained.
- Task details: main content/comments split, time tracking, estimate, subtasks, attachments, and separate edit/assignment/handoff/placement dialogs.
- Task edit: workflow, priority, dates, sprint, assignees and hours estimate; unchanged estimates are omitted from updates and protected fields respect existing permissions.
- Employee, department, role, workflow, profile and submission dialogs use consistent source sizing and field layouts.
- My Work: source filter ordering and shared issue cards.
- Reports: source filter grid, five metrics, department progress, searchable paginated table and exports.
- Work Logs and Team Activity: source table presentation and summary controls.
- Judibas header and shared appearance control are retained by explicit requirement.

## Verification

- Production Vue/TypeScript/Vite build passed.
- 21 PMS feature tests passed with 245 assertions.
- Browser checks passed for project list filters, preview, discussion, board navigation and stale project URL handling.
- Browser checks covered ten screen routes, shared header visibility, horizontal overflow, and assignment/project/employee/workflow/sprint/issue dialog dimensions.
- Task details, edit fields, assignment search, handoff, and mobile sprint dialog checks passed.
- Shared Light/Dark/Auto appearance and notification/account dropdown layering checks passed.

The checks establish the listed layout and interaction coverage; they are not an automated pixel-by-pixel comparison of every possible live state. Live data was inspected only; no reference records or mail were changed.
