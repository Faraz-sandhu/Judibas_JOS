# PMS access audit and task transfers

## Access rules

- Super Admin, Judibas Admin, and PMS Admin have unrestricted PMS access.
- `project-view-all` grants visibility across shared companies, clients, projects, and tasks. Viewing does not grant permission to edit or transfer.
- Normal employees see assigned tasks or subtasks in their selected companies, and only the related projects/clients.
- `task-view-team` grants visibility of work in the user's PMS departments, plus their individual assignments. Company membership still applies. Team-lead roles receive this permission by default; removing it returns them to individual assignment visibility.
- `task-transfer-company` grants the Transfer task action. It is not granted automatically to Project Managers or team leads. Assign it through PMS Roles & permissions when required.

## Transfers

Use Transfer task in a task card's action menu or My Work task details. Choose a destination company/client/project, department, and optional sprint. The destination must be visible to the actor and approved, the department must belong to it, and the sprint must belong to that department. A matching destination workflow stage preserves the current task status. Missing stages reject the transfer rather than silently resetting status.

Task IDs, comments, files, subtasks, estimates and logged time are retained. Running task/subtask timers block the transfer. Company-ineligible or inactive assignees are removed from the task and its subtasks; eligible assignees are retained. The destination gains project membership for retained task assignees. Employee company access is never granted through a task transfer.

The audit table `pms_task_transfers` stores the actor, reason, timestamp, and before/after project, company, client, department, workflow, sprint, status and assignments.

## Reports

Reports include Company -> Client -> Project filters. Client filters apply to summaries, task/subtask rows, Copy and CSV/XLSX/PDF exports. Report visibility uses the same own/team/company rules as boards.

## Validation

Transactional regression tests cover Super Admin/PMS admin access, own-task/subtask visibility, cross-company denial, team department visibility and permission removal, manager global visibility, separate transfer permission checks, preservation of task history, assignee cleanup, timer/destination rejection, combined boards and report client filters. Browser checks cover project/combined boards, dialogs, filters and sidebar navigation. These checks do not constitute a claim that every possible production state has been exercised.
