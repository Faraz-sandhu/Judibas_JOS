# PMS clients and company access

Navigation is Company -> Client -> Project -> Department -> Backlog / Sprints.

Create companies in Judibas Administration, then create clients in PMS Clients. A client belongs to one company. Projects inherit their company from their client and can include multiple departments. Select the project workflows for the participating departments; each department board automatically selects its own workflow (or the shared default). New sprints and tasks retain the chosen project and department.

Assign users to companies in Judibas Administration. Regular PMS members see only those companies and clients/projects where a task or subtask is assigned to them. Project membership alone does not expose the project. Department boards show their assigned work. Server-side visibility also applies to direct project and task URLs.

Super Admin, Judibas Admin, and PMS Admin have unrestricted PMS visibility. The permission `project-view-all` lets other authorized managers view the full company/project hierarchy. Project Managers receive it by default. Existing action permissions still govern edits, assignments, sprint management, and task operations. Client CRUD uses `client-add`, `client-edit`, and `client-trash`; normal project viewers may browse their visible clients. `client-view` also permits opening the Clients list; project viewers can browse their visible clients without requiring a separate client permission.

Existing projects are preserved. Admins can open Projects without a client / Projects to organize and use Edit project to select a client. Company records are linked to the shared Judibas companies by matching names; unmatched historical company records remain available for administration. No memberships are guessed or granted to existing users.

Older tasks receive a department only if their workflow or the project's single department identifies it. Sprints spanning several known departments are split while retaining their dates, goals, status, and tasks. Ambiguous records remain reachable under Existing work. Clients with projects cannot be deleted. Departments with tasks or sprints cannot be removed. Tasks cannot be moved to another department's sprint.

## Local sample data

The optional `App\Modules\Pms\Database\Seeders\PmsClientSampleSeeder` uses the companies already created in Judibas Administration. It creates realistically named sample clients, projects, department sprints, backlog tasks, and subtasks. It can be run repeatedly without duplicating records. It does not create shared companies, employee accounts, or send notifications.

The original PMS department names are Admin, Content Writer, Development, Ecommerce, Graphic, Management, Marketing, Project Manager, and SEO. The older sample departments Engineering and Creative Design are renamed when their corresponding original names do not already exist; their IDs and existing work are retained.

Company selectors and sidebar groups use shared dashboard companies. Creating or renaming a company in Administration synchronizes its PMS record. Historical standalone PMS companies are retained for existing data but are excluded from the company catalogue.

## Combined boards

The eye button beside a company, client, or project opens a combined sprint board for that level. All accessible descendant tasks are included by default, across workflows, departments, sprints, and backlog. Project, department, and sprint filters narrow the view. Card labels identify their client, project, department, and sprint. Department rows open their existing department board and have no eye button.

Combined board status changes map to a corresponding stage in the task's own workflow and reuse the existing task update authorization and transition rules. They do not move a task into another workflow or department. Filters are retained on refresh.
