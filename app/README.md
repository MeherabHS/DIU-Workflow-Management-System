# DIUS Project, Communication & Repository Management Portal

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

Laravel still owns routing, authentication, authorization, validation, database writes, redirects, and permissions. React owns page rendering, component composition, forms, and client-side UI behavior.

## Runtime Flow

1. The browser requests a normal Laravel route, for example `/projects`.
2. `routes/web.php` sends the request to the existing Laravel controller.
3. The controller checks permissions/policies and loads Eloquent data.
4. The controller returns `Inertia::render('Projects/Index', [...props])`.
5. Inertia loads `resources/js/Pages/Projects/Index.tsx`.
6. React renders the page using props provided by Laravel.
7. Forms submit to existing Laravel routes through Inertia `useForm`.
8. Laravel validates, writes to the database, and redirects back to an Inertia page.

The backend remains the source of truth.

## Key Files And Directories

- `resources/views/app.blade.php`
  - The only required Blade view.
  - Acts as the Inertia root shell.
  - Loads Vite React assets.

- `resources/js/app.tsx`
  - Boots the Inertia React app.
  - Resolves pages from `resources/js/Pages`.

- `resources/js/Layouts/AuthenticatedLayout.tsx`
  - Main authenticated app shell.
  - Provides the top header, user area, and role-aware navigation tabs.
  - Replaces the old Blade sidebar/layout.

- `resources/js/Components/Dius/ui.tsx`
  - Shared DIUS UI primitives.
  - Includes buttons, cards, status pills, priority pills, toolbars, detail grids, avatars, and module cards.

- `resources/js/lib/utils.ts`
  - Shared frontend helpers such as `cn`, `humanize`, date formatting, and initials.

- `resources/js/types/index.d.ts`
  - Shared TypeScript types for Laravel/Inertia props.
  - Covers auth props, users, projects, tasks, subtasks, repository entries, and pagination.

## Page Structure

React pages map directly to Laravel controller actions:

- `resources/js/Pages/Dashboard.tsx`
- `resources/js/Pages/Dashboards/RoleDashboard.tsx`
- `resources/js/Pages/Projects/*`
- `resources/js/Pages/Tasks/*`
- `resources/js/Pages/Subtasks/*`
- `resources/js/Pages/MySubtasks/*`
- `resources/js/Pages/Repository/*`
- `resources/js/Pages/Auth/*`
- `resources/js/Pages/Profile/*`

## Backend Connection Points

- `app/Http/Middleware/HandleInertiaRequests.php`
  - Shares global frontend props:
    - authenticated user
    - user roles
    - user permissions
    - flash messages
    - role-aware navigation
    - shared UI metadata

- Laravel controllers
  - Return `Inertia::render('Page/Name', [...])`.
  - Use existing Eloquent models and relationships.
  - Use existing policies, permissions, and form requests.
  - Redirect through normal Laravel redirects after mutations.

- React forms
  - Use Inertia `useForm`.
  - Submit to existing Laravel POST/PATCH routes.
  - Receive validation errors from Laravel through Inertia.

## Future Development Rules

- Add or modify UI in React/Inertia pages and components, not Blade feature views.
- Keep `resources/views/app.blade.php` as the Inertia root shell.
- Do not reintroduce Blade pages for project, task, subtask, repository, dashboard, auth, or profile UI.
- Keep Laravel controllers, policies, form requests, and models as the backend source of truth.
- For new backend data, expose it through controller Inertia props or existing Laravel routes, then render it in React.
- Do not assume Git is available in this workspace unless it is explicitly connected later.

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

Current verified status after the React/Inertia migration:

- `npm run build`: passed
- Full Laravel test suite: 147 tests passed, 1002 assertions
