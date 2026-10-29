# TaskFlow AI Handoff

This document gives a coding assistant enough context to continue TaskFlow without relying on a previous chat. Keep it current when architecture, workflows, or product scope change. It supplements `README.md`; the application code and database migrations are the source of truth.

## Product and current state

TaskFlow is an internal project and task-management application for software teams. It supports task planning and delivery across projects, teams, review, QA, and team sprints. The product is being built incrementally. Make one small, reviewable change at a time.

The core application is implemented: authentication, projects, teams and sharing, configurable project workflows, task management, project views, task collaboration, in-app notifications, and a team sprint lifecycle. Tasks can store one optional HTTPS GitHub reference, restricted to `github.com`, and changes appear in the task activity timeline. The UI has a project sidebar and project-level Overview, Board, List, Calendar, Reports, and Settings views. Reports use task activity for recent completion and update metrics, and current project task data for distributions, trends, and cycle/lead times. Board tasks open in a detail modal; task fields are shown as previews and edited one field at a time. Task creation can happen in a modal from the board.

The current editor is a dependency-free, contenteditable Blade component with formatting, paragraph/heading styles, lists, text colors, tables, code blocks, emoji, links, undo/redo, slash-command insertion, and image URLs. It is used for task/project/team descriptions, blocked reasons, and comments. Rich text is sanitized on the server. Images and attachments are URL references; there is no upload/storage flow.

## Stack and repository map

- PHP 8.3+, Laravel 13, Livewire 4, Tailwind CSS 4, Vite, SQLite locally.
- Composer dependencies are in `composer.json`; frontend dependencies are in `package.json`. Avoid adding a package unless it solves a concrete requirement.
- NativePHP mobile packages are currently Composer dependencies; inspect the mobile code and requirements before expanding that surface.
- `routes/web.php`: public pages and authenticated route registration. Routed app pages use `Route::livewire`.
- `resources/views/components/**/⚡*.blade.php`: Livewire 4 single-file page components (PHP class and Blade template together).
- `resources/views/components/`: shared Blade components, layouts, navigation, project tabs, editor.
- `app/Models/`: User, Team, Project, ProjectStatus, Task, Cycle, TaskComment, TaskActivity.
- `app/Policies/`: project, task, and team authorization policies.
- `app/Notifications/TaskNotification.php`: database notification payload for task events.
- `app/Support/RichText.php`: HTML allowlist and plain-text helpers for rich content.
- `database/migrations/`: schema history; add new migrations for schema changes, never rewrite applied migrations to change production data.
- `tests/Feature/`: app workflows, validation, access control. `tests/Unit/RichTextTest.php`: sanitizer behavior.
- `docs/PROJECT_PLAN.md`: original product plan; some sections may lag the current implementation.

PHP namespaces use the normal Laravel autoloading rules (`App\\` maps to `app/`). Routed Livewire component aliases omit the emoji and `.blade.php`, such as `projects.board` for `resources/views/components/projects/⚡board.blade.php`.

## Domain model and important rules

### Users, teams, and projects

- Users own projects and teams. Teams have an owner and members with `owner`, `admin`, or `member` roles.
- Projects belong to an owner and can optionally belong to a team. A project can also grant direct access through `project_members`.
- Effective project access is owner OR team member OR direct project member. `Project::scopeAccessibleTo()` is the standard list-query scope; `Project::isAccessibleBy()` checks one project.
- Project management is limited to the owner, team managers, and direct project admins. Team membership/settings are managed by the team owner/admin. Check the policies before changing a permission rule.
- Task assignees and reviewers must be selected from the project owner, team members, or direct project members.
- Directly shared project members do not automatically get team-cycle access. The cycle UI and task cycle assignment are restricted to members of the project's team.

### Project workflow and tasks

- Each project owns its `project_statuses`; status names and order are configurable. Do not hard-code task status values or accept a status from another project.
- Current default workflow is defined by `Project::DEFAULT_WORKFLOW_STATUSES`: To Do, In Progress, Complete, Verify, Closed, Blocked. The defaults mark Complete and Closed as complete; project settings let a manager choose a completion column, which makes that the single completion stage.
- Tasks use types `task`, `bug`, `feature`; priorities `low`, `medium`, `high`, `urgent`.
- Blocked tasks require a blocked reason. Leaving Blocked clears the reason in the current task workflow. A status that `ProjectStatus::requiresReviewer()` recognizes (Verify, In Review, QA, Quality Assurance) requires a reviewer on entry.
- Tasks can have a due date, assignee, reviewer, optional team sprint, and story point estimate (1, 2, 3, 5, 8, 13, or 21). Estimates are validated, guarded by SQLite triggers, tracked in activity, and aggregated into sprint progress.
- Team sprint choices are active or upcoming sprints from the same team; existing task assignments can be preserved after a sprint completes.
- Task deletion is restricted to the creator or project manager. Project members can view/update and comment on tasks they can access.
- Task create/update events write activity records and can send database notifications to relevant participants. Do not bypass model hooks casually; if bulk updates are introduced, preserve equivalent activity/notification behavior intentionally.

### Team sprints / cycles

- A cycle belongs to one team and has a name, optional goal while planned, start/end dates, status (`planned`, `active`, `completed`), and lifecycle timestamps.
- Sprint dates must not overlap. A sprint goal is required to start; team managers start and complete sprints; only one sprint can be active for a team.
- Completing a sprint moves tasks in non-complete workflow statuses to backlog or a selected upcoming sprint. Tasks in a project completion status remain attached to the completed sprint for reporting. Rollover updates use Eloquent so task activity and notifications run.
- Sprint overview aggregates current task and estimate completion. Historical daily burndown and immutable velocity snapshots are not implemented; per-day scope/progress is not stored.
- Direct project members cannot access or change team sprint assignments. Project team changes are restricted while tasks remain assigned to incompatible sprints.

### Comments, activity, and notifications

- `task_comments` belong to a task and author; `task_activities` keep a structured JSON `details` record and actor.
- Activity covers task creation and tracked changes to title, description, workflow status, priority, type, due date, assignee, reviewer, cycle, and blocked reason; comments also create activity entries.
- Notifications use Laravel's database notification table and are scoped to the authenticated user. Notification links/actions must re-check current project access; removing project access must not expose task data through an old notification.

### Rich text and content safety

- Use `<x-rich-text-editor>` instead of introducing raw textareas for long-form descriptions, comments, or blocked reasons.
- Persist sanitized editor HTML via `RichText::sanitize()`. Use `RichText::plainText()` for user-facing character limits and `RichText::isEmpty()` for required textual content.
- Any raw Blade HTML output (`{!! !!}`) must be sanitized at render or otherwise be trusted static HTML. Never render raw user HTML.
- The sanitizer currently allows basic formatting, headings, lists, code, blockquotes, safe links, tables, images over HTTP(S), and an approved color attribute. Attributes outside its allowlist are stripped. Keep sanitizer tests updated when editor features change.
- Image URLs are external references, not uploads. Do not imply private or durable attachment storage exists.

## Routes and UI areas

Public routes: `/`, `/features`, `/about`, `/login`, `/register`.

Authenticated routes include:

- `/dashboard`
- `/teams`, `/teams/{team}` (team access/settings and cycle management)
- `/projects`, `/projects/create`, `/projects/{project}` (overview), `/projects/{project}/board`, `/list`, `/calendar`, `/reports`, `/edit`
- `/projects/{project}/tasks/create`, `/projects/{project}/tasks/{task}/edit`
- `/tasks`, `/my-tasks`, `/notifications`, `/profile`, `/settings`

The project overview includes delivery metrics, workflow distribution, completion, overdue/due-soon counts, and recent project activity. The project board includes search and combined filters for priority, due date/preset and date range, type, assignee, reviewer, workflow status, reporter, cycle (when the viewer has team membership), and blocked tasks. The global task list has project, status, priority, type, due-date, and text search filters. Verify filter scoping when adding more options.

## Authorization and data safety checklist

For every new read or mutation:

1. Scope initial record lookup to the authenticated user's effective access (for example `Project::query()->accessibleTo(auth()->user())`). Do not fetch by ID first and rely only on hiding a link.
2. Call the relevant policy (`$this->authorize(...)`) for the requested action.
3. Validate on the server; scope foreign keys and IDs to the current project/team and the caller's accessible members.
4. Keep database foreign keys, uniqueness, and indexes in migrations where applicable.
5. Cover both an allowed path and a cross-team/cross-project denied path in feature tests.
6. Preserve existing data. Never run `migrate:fresh`, `db:wipe`, or delete/replace a database without the user's explicit approval. Do not overwrite `.env`.

## Development and verification

Read `AGENTS.md` before code work and follow any additional repository instructions. It currently asks agents to verify PHP/Composer and install Laravel Boost before application changes. PHP and Composer are available in this environment, but Boost installation previously failed because Composer could not reach Packagist (DNS/network unavailable); do not claim Boost is installed unless the package and setup are actually present.

Typical local commands:

```bash
composer install
# Create .env and its key only for a new local setup; never replace an existing key.
if [ ! -f .env ]; then cp .env.example .env && php artisan key:generate; fi
# Ensure the configured SQLite file exists before first migration.
php artisan migrate
npm install
npm run build
php artisan test --compact
```

For existing local data, inspect `.env` and the SQLite path and make a backup before migrations. Do not use a destructive migration/reset command. Tests configure an in-memory SQLite database in `phpunit.xml` and use `RefreshDatabase`.

Use the project's existing single-file Livewire convention. Keep business logic close to the component/model until there is a clear reason for a new service or abstraction. Keep Tailwind styling consistent with existing components and retain responsive layouts, labels, focus states, empty states, validation feedback, and loading/disabled states.

When finishing code work, report changed files, relevant test/build results, risks, and useful follow-up. For documentation-only changes, do not claim application tests were run unless they were.

## Current gaps and sensible next work

The current app has sprint planning/lifecycle and aggregate progress, project reporting/overview, task comments/activity, in-app notifications, inline review/QA assignment, and board filters. Avoid re-implementing those as missing features.

Potential next increments, after checking the live code and tests:

1. Historical sprint reports (burndown, scope changes, velocity) after defining and storing immutable snapshots or sufficient task history.
2. Reusable project/task templates and recurring task creation if the team confirms a real workflow need.
3. Optional GitHub pull request status sync, time tracking, and more advanced automations only after defining the smallest useful workflow and permission model.

Do not implement a whole roadmap in one change. First inspect the relevant current implementation, then choose one bounded feature and define its acceptance criteria and tests.
