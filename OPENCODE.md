# OpenCode instructions for TaskFlow

You are helping build a learning project, not just generating code.
Explain important decisions briefly and implement one small phase at a
time.

## Project constraints

-   Laravel 13.38.x
-   PHP 8.4.x currently installed (minimum requirement: PHP 8.3)
-   Livewire 4.4.x
-   Tailwind CSS 4
-   SQLite local database
-   Manual Laravel session authentication
-   User is learning through guided implementation

## First task

1.  Inspect `composer.json`, `package.json`, `vite.config.js`,
    `routes/web.php`, the app layout, and the generated Livewire
    registration component.
2.  Confirm the current component convention. The generator produced
    `resources/views/components/auth/⚡register.blade.php`, a Livewire 4
    single-file component.
3.  Diagnose the current blank registration page and the previous
    `InvalidArgumentException: No hint path defined for [layouts]`.
4.  Fix the actual layout/component/route issue using documented
    Livewire 4 conventions. Do not add unnecessary namespace
    configuration or duplicate layouts.
5.  Implement a polished, responsive registration page matching
    `docs/design/taskflow-ui-overview.png` and the design system in
    `docs/PROJECT_PLAN.md`.
6.  Keep Tailwind CSS 4 setup compatible with the current Vite config.
7.  Verify that the users table migration exists, SQLite is configured,
    and the registration flow validates input, hashes passwords, creates
    a user, logs in, regenerates the session, and redirects to a valid
    dashboard route.
8.  Add a minimal dashboard page/layout only as needed to make the
    post-registration redirect work.
9.  Run relevant checks: route list, migration status, test suite (or
    focused tests), and `npm run build`.
10. Report changed files, commands run, results, and any remaining
    issue.

## Design system

-   Theme: Modern Minimal.
-   Primary: indigo `#4F46E5`; hover `#4338CA`.
-   Secondary: blue `#3B82F6`.
-   Background: `#F8FAFC`; surfaces: white.
-   Main text: `#0F172A`; muted text: `#64748B`; borders: `#E2E8F0`.
-   Success: `#059669`; warning: `#D97706`; danger: `#DC2626`.
-   Use rounded-lg inputs, rounded-xl cards, subtle borders/shadows,
    visible focus rings, clear labels, accessible error messages,
    responsive spacing.
-   Do not use color alone to communicate status.
-   Prefer a dark navy sidebar for authenticated pages and a centered,
    clean auth card for register/login.

## Learning and safety rules

-   Inspect before editing; preserve working user changes.
-   Do not overwrite `.env`, database contents, or user work.
-   Do not run destructive database commands (`migrate:fresh`,
    `db:wipe`, `migrate:reset`) without asking first.
-   Do not add dependencies unless necessary.
-   Do not implement all planned phases at once.
-   Explain why each important change is needed.
-   Add tests for auth and ownership behavior.
-   Use the current Livewire 4 single-file component convention
    consistently.
