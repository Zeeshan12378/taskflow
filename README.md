# TaskFlow

TaskFlow is an internal project and task management application for teams doing software development, bug tracking, review, QA, planning, and delivery. It is built with Laravel 13, Livewire 4 single-file components, Tailwind CSS 4, Vite, and SQLite for local development.

For an AI-oriented architecture and continuation guide, read [docs/AI_HANDOFF.md](docs/AI_HANDOFF.md). The broader product plan is in [docs/PROJECT_PLAN.md](docs/PROJECT_PLAN.md); the application code and migrations are authoritative when the plan is out of date.

## What is implemented

- Session-based registration, login, logout, profile, and account settings, including theme preference.
- Dashboard with project and task summaries.
- Teams with owners, admins, and members. Team members inherit access to team projects; projects can also grant direct access.
- Projects with overview, board, list, sprint-grouped task list, calendar, reports, and settings views. Reports include task distributions, recent activity and completion trends. Projects have configurable workflow columns and a completion column.
- Task types (`task`, `bug`, `feature`), priorities (`low`, `medium`, `high`, `urgent`), due dates, blocked reasons, assignees, reviewers, and story point estimates on the Fibonacci scale (1–21).
- Optional HTTPS GitHub references on tasks, restricted to `github.com` URLs and recorded in task activity.
- Board search and filters for workflow status, type, priority, due dates, date ranges, assignee, reviewer, reporter, sprint, and blocked tasks. Drag cards to reorder within or move between columns; manual sorting is disabled while filters are active.
- Jira-style quick task creation at the bottom of each board column; review and blocked columns use the full form when reviewer or blocked-reason details are required.
- Task detail modal with inline field editing, comments, activity history, and in-app notifications for task events.
- Tasks support optional checklists with up to 30 items, completion tracking, and checklist progress in task activity. Saved project templates and weekly recurring tasks preserve checklist steps while starting each new task with all steps incomplete.
- Project managers can create weekly recurring task schedules from saved project templates. Each scheduled task is due on its occurrence date; schedules can be paused, resumed, or deleted. Configure the host to run `php artisan schedule:run` every minute for recurring tasks to be generated.
- Independent team and project sprint planning with dates, goals, and overlap validation. Managers start and complete sprints, with at most one active sprint per team and per project, and unfinished tasks can roll to backlog or another planned sprint. Project sprints are visible to project members; team sprints remain limited to team members. Accessible project and team sprint choices are available on project boards and task forms.
- Sprint reports with a start baseline, daily scope/progress snapshots, completion snapshots, and historical delivered-point velocity. Configure the host to run `php artisan schedule:run` every minute for daily samples.
- Reusable rich-text editor for task, project, and team descriptions, blocked reasons, and comments. It supports formatting, lists, headings, tables, code blocks, emoji, links, text colors, and images by URL. HTML is sanitized server-side; the editor does not upload files.
- Feature and unit tests, including authorization, validation, team and project sprints, notifications, and rich-text sanitization.

## Requirements

- PHP 8.3 or later and Composer
- Node.js and npm
- SQLite with the PDO SQLite PHP extension

## Local setup

Run from the repository root:

```bash
composer install
```

Create `.env` and generate an application key only for a new local installation. Keep your current `.env` and key when continuing an existing installation:

```bash
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate
fi
```

Set `DB_CONNECTION=sqlite` in `.env`. The default SQLite file is `database/database.sqlite`; create it only if that is the path configured for this installation and the file is missing:

```bash
if [ ! -f database/database.sqlite ]; then touch database/database.sqlite; fi
```

For an existing database, inspect the environment and take a backup before applying pending migrations. `php artisan migrate` applies migrations without resetting existing data. Do not use `migrate:fresh` or database reset commands against data you want to keep.

Install and build frontend dependencies:

```bash
npm install
npm run build
```

Run the local server:

```bash
php artisan serve
```

Then open <http://127.0.0.1:8000>. For local development with Vite hot reload, run `npm run dev` in a second terminal.

## Useful commands

```bash
php artisan route:list
php artisan migrate:status
php artisan test --compact
npm run build
php artisan schedule:list
```

The test suite uses SQLite in memory through `phpunit.xml` and `RefreshDatabase`; tests do not require resetting the developer's local database.

## Main routes

| Route | Purpose |
| --- | --- |
| `/` | Public landing page |
| `/features`, `/about` | Public product pages |
| `/register`, `/login` | Guest authentication |
| `/dashboard` | Authenticated summary |
| `/teams`, `/teams/{team}` | Team membership and cycle management |
| `/projects` | Project list |
| `/projects/{project}` | Project overview |
| `/projects/{project}/board` | Workflow board and task detail modal |
| `/projects/{project}/list`, `/calendar` | Alternative project views |
| `/projects/{project}/sprints` | Tasks grouped by each accessible sprint and backlog |
| `/projects/{project}/reports` | Project task, status, assignee, and completion reports |
| `/projects/{project}/edit` | Project settings |
| `/tasks`, `/my-tasks` | Task list and filters |
| `/notifications` | In-app notifications |
| `/profile`, `/settings` | Account and preferences |

All application routes except the public landing, information, login, and registration pages require authentication.

## Project conventions

- Routed Livewire pages are single-file components under `resources/views/components/**/⚡*.blade.php` and are registered with `Route::livewire` in `routes/web.php`.
- Shared Blade components live alongside those pages (for example, `resources/views/components/projects/tabs.blade.php` and `resources/views/components/rich-text-editor.blade.php`).
- Models, policies, notifications, and rich-text sanitization live in `app/`.
- Schema changes belong in new additive migrations under `database/migrations/`. Preserve existing user data.
- Every mutation must validate input and authorize on the server. Scope reads by effective team/project membership; do not trust hidden controls or client-supplied IDs.
- Add or update feature tests for behavior and authorization changes. Run relevant tests and `npm run build` after implementation.
- Keep changes small and reviewable; do not add dependencies or architecture layers without a concrete need.

## Documentation

- [AI handoff and project guide](docs/AI_HANDOFF.md)
- [Product plan and original specification](docs/PROJECT_PLAN.md)
