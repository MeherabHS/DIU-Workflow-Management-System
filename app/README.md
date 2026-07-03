# DIUS Project, Communication & Repository Management Portal

DIUS Management Portal is a Laravel + Inertia React MVP for managing the Project -> Task -> Work Item workflow, role-based dashboards, repository records, communications, notifications, and evidence uploads.

## Current MVP Scope

- Role-based access for Admin, PM/Manager, Coordinator, and Subordinate users.
- Left sidebar navigation generated from Laravel permissions and roles.
- Role dashboards for Admin, PM/Manager, Coordinator, and Subordinate users.
- Project -> Task -> Work Item hierarchy.
- Coordinator assignment at project level.
- Subordinate assignment at Work Item level.
- My Work Items workspace for Subordinates.
- Repository Tracker for preserved project records and timeline updates.
- Workflow messages, notifications, attachments, and evidence uploads.
- Profile photo upload with initials fallback.
- Graceful frontend loading and error fallback screens for slow starts or runtime errors.

## Frontend Architecture

The frontend is a Laravel Inertia React frontend. It is not a standalone Next.js app and it is not a separate SPA server.

Current frontend stack:

- React 18
- TypeScript
- Inertia.js React
- Tailwind CSS
- Vite
- lucide-react
- class-variance-authority
- framer-motion
- tw-animate-css

Laravel owns routing, authentication, authorization, validation, database writes, redirects, and permissions. React owns page rendering, component composition, forms, and client-side UI behavior.

## Runtime Flow

1. The browser requests a normal Laravel route, for example `/projects`.
2. `routes/web.php` sends the request to a Laravel controller.
3. The controller checks permissions/policies and loads Eloquent data.
4. The controller returns `Inertia::render('Projects/Index', [...props])`.
5. Inertia loads the matching page from `resources/js/Pages`.
6. React renders the page using props provided by Laravel.
7. Forms submit to Laravel routes through Inertia `useForm`.
8. Laravel validates, writes to the database, and redirects back to an Inertia page.

The backend remains the source of truth.

## Navigation And Roles

The authenticated layout uses a left sidebar plus a compact top account bar. Navigation links are shared from `app/Http/Middleware/HandleInertiaRequests.php` and are filtered by role and permission.

Expected role flow:

- Admin: oversight, users, audit trail, reports, projects, repository.
- PM/Manager: project management, coordinator assignment, reports/repository when permitted.
- Coordinator: assigned projects, tasks, Work Items, subordinate assignment.
- Subordinate: My Work Items, progress updates, evidence uploads.

## Status And Terminology

Project statuses shown to users are:

- Planned
- In Progress
- Submitted
- Completed
- Archived
- Cancelled

Client-facing UI should say Work Item instead of Subtask. Internal model, table, and route names still use `Subtask`/`subtasks` for compatibility and should not be renamed casually.

The dashboard project-count KPI is labeled Total Projects.

## Key Files And Directories

- `resources/views/app.blade.php`
  - Inertia root shell and pre-React loading fallback.

- `resources/js/app.tsx`
  - Boots the Inertia React app, configures navigation progress, and wraps the app in an error boundary.

- `resources/js/Layouts/AuthenticatedLayout.tsx`
  - Main authenticated shell around the left sidebar and top account bar.

- `resources/js/Components/Dius/ui.tsx`
  - Shared DIUS UI primitives.

- `resources/js/Components/WorkManagement/*`
  - Workflow-specific cards, badges, file/message/notification UI, loading fallback, and error boundary.

- `resources/js/lib/utils.ts`
  - Shared frontend helpers such as `cn`, `humanize`, date formatting, and initials.

- `resources/js/types/index.d.ts`
  - Shared TypeScript types for Laravel/Inertia props.

## Page Structure

React pages map directly to Laravel controller actions:

- `resources/js/Pages/Dashboard.tsx`
- `resources/js/Pages/Dashboards/*`
- `resources/js/Pages/Projects/*`
- `resources/js/Pages/Tasks/*`
- `resources/js/Pages/Subtasks/*` for Work Item screens
- `resources/js/Pages/MySubtasks/*` for My Work Items screens
- `resources/js/Pages/Repository/*`
- `resources/js/Pages/Notifications/*`
- `resources/js/Pages/Auth/*`
- `resources/js/Pages/Profile/*`

## Render Deployment Notes

- `start.sh` clears config, runs migrations, syncs role permissions, links public storage, clears optimized caches, and starts Laravel on `${PORT:-8000}`.
- `php artisan storage:link` is expected so profile photos can be served from `/storage/...`.
- Workflow files are stored on the local/private disk and downloaded through authorized Laravel routes.
- Profile photos use the public disk. On free prototype services, persistent storage may reset across redeploys unless a persistent disk is configured.
- Docker PHP upload limits are set above the app-level 2 MB profile photo validation limit so Laravel can return clear validation errors.
- Use production-safe environment values for handover, especially `APP_DEBUG=false`, a real `APP_URL`, and database credentials managed by the host.

## Future Development Rules

- Add or modify UI in React/Inertia pages and components, not Blade feature views.
- Keep `resources/views/app.blade.php` as the Inertia root shell.
- Do not reintroduce Blade pages for project, task, Work Item, repository, dashboard, auth, or profile UI.
- Keep Laravel controllers, policies, form requests, and models as the backend source of truth.
- For new backend data, expose it through controller Inertia props or existing Laravel routes, then render it in React.
- Do not rename `Subtask` models/tables/routes unless a dedicated compatibility migration plan exists.

## Verification Commands

Use the configured PHP path when running backend tests:

```powershell
& "G:\Tools\php\php.exe" artisan optimize:clear
& "G:\Tools\php\php.exe" artisan test
```

Frontend build:

```powershell
npm run build
```

Current handover checks should include:

- `npm run build`
- `php artisan optimize:clear`
- role boundary/visibility tests
- workflow notification tests
- project assignment workflow tests
- full Laravel test suite before final release