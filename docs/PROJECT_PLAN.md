# TaskFlow --- Project Plan and Product Specification

## 1. Project overview

**TaskFlow** is a practice project for learning full-stack Laravel
development by building a polished project and task management
application.

### Goals

-   Build a responsive UI with Tailwind CSS 4.
-   Learn Livewire 4 single-file components and reactive forms.
-   Practice Laravel authentication, validation, authorization, Eloquent
    relationships, and database migrations.
-   Use SQLite locally.
-   Add automated tests and production-minded security practices.
-   Keep implementation incremental so each feature can be understood
    and tested.

### Technology stack

-   Laravel 13
-   PHP 8.3+
-   Livewire 4
-   Tailwind CSS 4 + Vite
-   SQLite for local development
-   Laravel session authentication
-   PHPUnit or Pest, depending on the project's existing test setup
-   Git for version control

## 2. Product scope

### MVP features

1.  Registration, login, logout, and validation.
2.  Authenticated dashboard with summary cards.
3.  Project CRUD.
4.  Task CRUD, priority, due date, and optional assignee.
5.  A Jira-style board for each project, with task cards grouped into
    configurable workflow columns.
6.  Search, filtering, and pagination.
7.  Authorization so users can access only their own projects, workflow
    statuses, and tasks.
8.  Feature tests for authentication, project CRUD, task CRUD, workflow
    status management, and access control.
9.  Responsive empty states, validation feedback, loading states, and
    error states.
10. Teams and project-specific access, with tasks assigned to one project
    member at a time.

Each project starts with the configured workflow columns **To Do**, **In
Progress**, **Complete**, **Verify**, **Closed**, and **Overdue**. Project
owners can add, rename, reorder, and remove empty columns, and choose
which column represents completion. Tasks move between columns by drag
and drop on the board; provide an accessible status selector as an
alternative. Columns are project-specific, so each project can use a
workflow that fits its work.

The board shows one column per workflow status and task cards with the
title, priority, and due date. Show each column's task count, an empty
state, and clear feedback when a task changes status. On narrow screens,
allow horizontal board scrolling and keep the status selector available.

### Later enhancements

-   Teams and invitations
-   Database notifications
-   Queued email notifications
-   Activity timeline
-   File attachments
-   Docker and CI workflow
-   Deployment

Do not build the later enhancements until the MVP is stable and tested.

## 3. Design direction

### Theme

**Modern Minimal** --- clean, calm, professional, product-focused.

### Visual principles

-   White or very light gray content surfaces.
-   Deep navy sidebar for authenticated pages.
-   Indigo-to-blue primary actions.
-   Rounded-lg controls and rounded-xl cards.
-   Subtle borders and restrained shadows.
-   Clear hierarchy, generous spacing, readable labels.
-   Responsive layouts that work on mobile and desktop.
-   Accessible focus indicators and adequate color contrast.

### Color tokens

  Purpose          Color       Tailwind suggestion
  ---------------- ----------- ---------------------------
  Primary          `#4F46E5`   `indigo-600`
  Primary hover    `#4338CA`   `indigo-700`
  Secondary        `#3B82F6`   `blue-500`
  App background   `#F8FAFC`   `slate-50`
  Card surface     `#FFFFFF`   `white`
  Main text        `#0F172A`   `slate-900`
  Muted text       `#64748B`   `slate-500`
  Border           `#E2E8F0`   `slate-200`
  Success          `#059669`   `emerald-600`
  Warning          `#D97706`   `amber-600`
  Danger           `#DC2626`   `red-600`
  Sidebar          `#172033`   custom navy / `slate-900`

Use semantic colors consistently. Do not use color alone to communicate
status; include status text.

### Typography and components

-   Use the system font initially; add a custom font only if necessary.
-   Page titles: `text-2xl` or `text-3xl`, bold.
-   Body text: `text-sm` to `text-base`.
-   Inputs: full width, rounded-lg, visible focus ring.
-   Buttons: primary, secondary, danger, and disabled states.
-   Cards: white surface, subtle border, rounded-xl.
-   Use Lucide icons only if the project already has an icon package or
    one is deliberately installed.

## 4. Pages and user journeys

### Public pages

-   `/register`: name, email, password, confirmation.
-   `/login`: email, password, remember-me (optional), password
    visibility toggle (optional).
-   Future `/`: simple product landing page, only if time permits.

### Authenticated pages

-   `/dashboard`: project count, task count, completed tasks, overdue
    tasks, recent projects, upcoming tasks.
-   `/projects`: list, search, filters, create action.
-   `/projects/create` and `/projects/{project}`: create and view/edit
    project.
-   `/projects/{project}/board`: project board with configurable status
    columns, task cards, drag-and-drop movement, and a keyboard-accessible
    status selector.
-   `/tasks`: task list, workflow-status/priority filters, due date,
    project.
-   `/profile`: account details (optional MVP).

### Required UI states

Every data page should include: - Loading state - Empty state with a
clear next action - Validation errors - Success feedback - Failure
feedback - Disabled state during submission - Responsive mobile layout

## 5. Data model proposal

### users

Use Laravel's default users table. Passwords must be hashed. Email must
be unique.

### projects

-   id
-   user_id (owner)
-   name
-   description (nullable)
-   status (e.g. active, archived)
-   timestamps

### tasks

-   id
-   project_id
-   user_id (creator/owner)
-   title
-   description (nullable)
-   project_status_id (workflow column; must belong to the same project)
-   priority (low, medium, high)
-   due_date (nullable)
-   timestamps

### project_statuses

Workflow columns belong to a project rather than being hard-coded task
enum values. Seed each new project with the configured defaults **To
Do**, **In Progress**, **Complete**, **Verify**, **Closed**, and
**Overdue** in that order.

-   id
-   project_id
-   name
-   position (column order)
-   is_complete (whether tasks in this column count as completed)
-   optional color token for the column accent
-   timestamps

Use a unique `(project_id, name)` pair and an index on
`(project_id, position)`. Keep the project lifecycle field (`active` or
`archived`) separate from task workflow statuses.

Status names and order can be changed per project. A project must retain
at least one status marked complete. Do not remove a status that still
contains tasks; require those tasks to be moved to another status first.
Validate that a task's selected status belongs to its project. Scope all
status management through the owning project.

### Relationships

-   User has many Projects.
-   Project belongs to User.
-   Project has many Tasks.
-   Project has many ProjectStatuses, ordered by `position`.
-   ProjectStatus belongs to Project and has many Tasks.
-   Task belongs to Project.
-   Task belongs to ProjectStatus.
-   User has many Tasks if `user_id` is used as the task creator/owner.

Add foreign keys and indexes deliberately. A task must not be accessible
merely because a user guesses its ID; enforce ownership using policies
and scoped queries. Keep task `project_id` and `project_status_id`
consistent, and prevent deleting a status while tasks still reference it.

## 6. Architecture rules

-   Use Livewire 4 single-file components consistently if keeping the
    generated `resources/views/components/.../⚡...blade.php`
    convention.
-   Do not mix single-file component routes with traditional class-based
    component examples without an explicit reason.
-   Keep route definitions simple.
-   Use Eloquent relationships and scoped queries for ownership.
-   Use Laravel validation on the server for every mutation.
-   Use `Hash::make()` for passwords and Laravel's `Auth` facade for
    session authentication.
-   Protect authenticated routes with `auth`; restrict guest-only pages
    with `guest`.
-   Add policies or equivalent authorization checks before project/task
    mutations.
-   Treat workflow columns as project-owned records, not a fixed enum.
    Validate status ownership whenever a task is created, moved, or
    updated; authorize status changes through the owning project.
-   Avoid introducing repository classes or service classes until
    business logic justifies them.
-   Do not add a package just because it is popular; inspect current
    dependencies first.

## 7. Suggested delivery plan

### Phase 0 --- Verify environment

-   Confirm Laravel 13, PHP 8.3+, Livewire 4, Tailwind 4, Vite, and
    SQLite.
-   Run migrations.
-   Build frontend assets.
-   Commit the clean baseline.

### Phase 1 --- Authentication

-   Registration UI and server-side validation.
-   Login UI and authentication.
-   Logout.
-   Guest/auth middleware.
-   Login throttling and feature tests.
-   Verify password hashing and session regeneration.

### Phase 2 --- App shell and dashboard

-   Shared layout and navigation.
-   Responsive sidebar/top bar.
-   Dashboard summary cards.
-   Empty states for new accounts.

### Phase 3 --- Projects

-   Migration, model, and relationships.
-   Project list and create/edit/delete.
-   Search, pagination, validation.
-   Ownership policies and tests.
-   Create the project's default workflow columns when a project is
    created.

### Phase 4 --- Tasks

-   Migration, model, relationships.
-   Create/edit/delete tasks.
-   Dynamic project workflow statuses instead of a hard-coded task
    status enum.
-   Jira-style board with configurable columns and task cards.
-   Add, rename, reorder, and safely remove workflow columns; let the
    project owner select its completion column.
-   Move tasks between columns by drag and drop and through an
    accessible status selector.
-   Priority and due date.
-   Search, filters, pagination.
-   Authorization and workflow status tests, including ensuring a task
    cannot use a status from another project.

### Phase 4.2 --- Public marketing pages

-   Replace the Laravel starter homepage with a responsive TaskFlow
    product landing page.
-   Add public Features and About pages with working navigation and
    clear paths to registration and login.
-   Describe only the project, task, and configurable board features
    that are already implemented.
-   Reuse the light/dark brand assets and the existing design system.

### Phase 5 --- UX polish (complete)

-   Responsive page layouts and controls, including flexible project
    actions and horizontally scrollable workflow boards.
-   Loading feedback and disabled duplicate-submit controls for forms,
    board changes, and project/task filtering and deletion.
-   Accessible success and failure announcements, field-linked validation
    errors, keyboard focus indicators, and labeled controls.
-   Useful empty states with clear-filter and next-action links.
-   Keep paginated project and task lists on a populated page after
    deleting the final item on a later page.

### Phase 6 --- Quality (complete)

-   Run the full test suite and cover public marketing pages, access
    control, validation, workflow failure cases, and populated pagination.
-   Review relationship queries and eager load related task, status, and
    project data on lists and the dashboard to prevent N+1 queries.
-   Review workflow and ownership validation, foreign keys, and indexes.
-   Run `npm run build` and compile Blade views.

### Phase 7 --- Documentation and deployment preparation

-   README setup steps.
-   Environment variable notes.
-   Database backup/reset instructions.
-   CI checks.
-   Deployment only after tests pass.

### Phase 7.1 --- Teams and project collaboration

-   Create teams with an owner, admins, and members; add existing TaskFlow
    accounts and manage their membership.
-   Team members inherit access to projects assigned to their team.
    Projects may also grant direct access to people outside the team.
-   Project owners and admins manage project sharing; team owners and
    admins manage team membership. Members can work with project tasks.
-   Tasks have one optional assignee, selected from the project's owner,
    team members, or direct project members. Support unassigned and
    "Assign to me" choices.
-   Preserve existing personal projects and restrict every project, task,
    team, and assignment query by effective membership.
-   Only registered TaskFlow accounts can be added; email invitations are
    a later enhancement.
-   Design references: [Trello board members and card members](https://support.atlassian.com/trello/docs/adding-people-to-a-board/),
    [Jira project roles](https://support.atlassian.com/jira/kb/grant-access-to-single-jira-project-that-uses-a-shared-permission-scheme/),
    and [ClickUp hierarchical permissions](https://help.clickup.com/hc/en-us/articles/6309221065495-Permissions-in-detail).

## 8. Definition of done

A feature is done only when:

1.  The UI works on desktop and mobile.
2.  Validation is enforced server-side.
3.  Authorization prevents cross-user access.
4.  Success, failure, loading, and empty states are handled.
5.  Relevant automated tests pass.
6.  No secrets are committed.
7.  The feature is documented briefly.
8.  Workflow columns are configurable per project, and tasks can only
    use a status owned by their project.

## 9. Local setup checklist

From the project root:

``` bash
php artisan --version
composer show livewire/livewire
php -m | grep -Ei 'pdo_sqlite|sqlite3'
php artisan migrate:status
npm run build
```

Use `.env` with `DB_CONNECTION=sqlite` and ensure
`database/database.sqlite` exists.

## 10. Important current implementation note

The current Livewire 4 generator created a single-file component at:

`resources/views/components/auth/⚡register.blade.php`

Before changing routes or layout, inspect the generated component and
the installed Livewire 4 documentation. Ensure the route points to the
actual single-file component and that the component has a valid layout
configuration. Do not copy a class-based Livewire component example
directly over the single-file component.

## 11. Next action

Continue one small, testable phase at a time. Authentication, the app
shell/dashboard, project CRUD, task management, the configurable project
board, Phase 4.2 public marketing pages, Phase 5 UX polish, Phase 6
quality review, and Phase 7.1 team/project collaboration are implemented.
Proceed to Phase 7 documentation and deployment preparation. Do not
generate the entire application in one unreviewed change.
