# PMS migration verification

Source: PMS-PROJECT, retained unchanged. source-manifest.json records the original source hashes. This document describes connected migration work and evidence; it does not certify every possible historical data combination or pixel-identical rendering.

## Module boundaries

Backend: app/Modules/Pms. Vue: resources/js/modules/pms. PostgreSQL tables: pms_ prefix in the Judibas database. Shared account IDs support future cross-product integrations, while PMS permissions, assignments, settings, notifications and realtime channels stay separate. Client companies remain separate from Communication employee communities. Removing an employee from PMS preserves their shared account and other product access.

## Connected screens and actions

| Area | Migrated behavior | Verification |
|---|---|---|
| Access | Shared login, product grant, inactive account denial, module Admin and Super Admin bypass, role permission union | Feature tests |
| Departments and roles | Create, edit, status, permanent deletion, unique names/keys, permission selection and sync | Feature tests and browser |
| Permission catalog | Create, rename with immutable permission key, permanent deletion | Feature test |
| Employees/profile | Create/edit, role and department assignment, module removal, own name/photo/password, current-password validation | Feature tests and browser |
| Projects | Normalized names, clients, dates, files/URL, workflows/departments, assignment/removal, lifecycle, approval, edit/delete | Feature tests and browser |
| Tasks/subtasks | Create/edit/delete, independent status/priority/deadline/description permissions, management approval/rejection, assignments/removal, rich formatting, attachments and completion summaries | Populated feature tests |
| Boards | Projects, My Work, Team Space, department backlog, workflow columns, any/adjacent transitions, project card insertion position | Feature tests and browser; touch interactions require device testing |
| Sprints | Create, edit, complete, delete and move tasks back to backlog | API tests and connected Vue controls |
| Handoffs/placement | Source completion, destination task, department backlog, linked attachments, lead assignments, chain protection, approved-project placement | Populated feature tests |
| Time | Task/subtask start/pause/stop, running timer protection, removed-assignee timer closure, estimates/history and work logs | Feature tests |
| Collaboration | Comments, project discussions/replies/seen/own deletion, permission-scoped private messaging, cursor pagination, favorites, shared photos, private attachment access and original conversation deletion | Feature tests and browser; private Messages is enabled here although hidden in the latest source sidebar |
| Invitations | Employee onboarding, task email/manual invitations, existing employee collaborator invites, owner-only accept/decline, expiry and one-time use | Feature tests |
| Notifications/realtime | Database notifications, unread/read menu, module-private Reverb or module Pusher settings, board/task/chat updates, isolated credential configuration | Channel and configuration feature tests; external Pusher delivery requires real deployment testing |
| Reporting | Role dashboards, company/project/task/subtask/department/employee/date/deadline filters, work logs, CSV/XLSX/PDF/copy exports | Populated export tests and browser |
| Team Activity | Original recent-activity classification, request activity updates and visible workspace heartbeat | Connected UI; not the Communication presence system |
| Settings | Branding assets, SMTP notification/invitation flags, quota controls, test email, Reverb/Pusher toggle/credentials and connection test | Browser/API checks; no real test email was sent |
| System Health | Original Laravel Pulse dashboard adapted to Laravel 12 and protected by PMS permission | Feature test and browser |
| Offline | Module-scoped service worker, static offline fallback and install manifest; no private API or project response caching | Build/source verification; HTTPS or localhost required |
| Maintenance | Original reminder threshold retained, daily deduplication corrected, optional 09:00 reminders, original approved-task cleanup command | Rollback-only command tests; permanent cleanup schedule remains disabled pending explicit approval |

## Deliberate adaptations

Laravel 12/Pulse compatibility, PostgreSQL JSONB/queries, shared accounts, nullable Super Admin actor IDs with audit names, private namespaced realtime transport and Vue forms replace Blade response rendering. Original PMS client companies are not automatically merged with Communication companies. Controller create/edit/show stubs with no source behavior were not turned into invented features. No department report-template scenario was found in the active source.

## Limits and operation

Historical source database records and uploaded files have not been imported. The original application is unchanged. A production import needs explicit ID mapping for shared accounts and the source database/files. Browser checks cover screen loading, not every mobile viewport or every role/data combination. Live SMTP/Pusher delivery must be checked against deployment credentials.

Open /pms through the Projects product. Use the existing Laravel, frontend build and Reverb startup commands. Reminder scheduling is off unless PMS_REMINDERS_ENABLED=true; use php artisan schedule:work when enabled. Midnight permanent deletion is not registered: automatic approval review rejected enabling broad irreversible deletion without explicit approval. The command remains available for separately authorized use.

## Final verification (2026-10-09)

Production Vue/TypeScript build passed. Full application regression suite: 110 tests, 894 assertions passed. Final PMS-only suite: 20 tests, 236 assertions passed. Browser checked 17 Vue screens plus Laravel Pulse without page errors or failing PMS API requests. Source hash audit: 309 original files checked, zero changes. No historical data import or external test email was performed.
