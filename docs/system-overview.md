# DIUS Management Portal — System Overview

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        Client (Browser)                      │
│  React 18 + Inertia.js + TypeScript + Tailwind CSS          │
───────────────────────┬─────────────────────────────────────┘
                        │ HTTP/JSON
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                     Laravel 11 Backend                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Routes     │  │ Controllers │  │   Middleware        │  │
│  │  (web.php)  │  │  (REST)     │  │  (auth, permission) │  │
│  └────────────┘  └──────┬──────┘  └──────────┬──────────┘  │
│         │                │                     │             │
│         ▼                ▼                     ▼             │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐  │
│  │  Policies   │  │  Services   │  │  Form Requests      │  │
│  │  (authz)    │  │  (business) │  │  (validation)       │  │
│  └──────┬──────┘  └──────┬──────┘  └──────────┬──────────┘  │
│         │                │                     │             │
│         ▼                ▼                     ▼             │
│  ┌─────────────────────────────────────────────────────────┐ │
│  │                    Eloquent Models                        │ │
│  │  User, Project, Task, Subtask, RepositoryEntry, etc.     │ │
│  └─────────────────────────────────────────────────────────┘ │
───────────────────────┬─────────────────────────────────────┘
                        │ Eloquent ORM
                        ▼
┌─────────────────────────────────────────────────────────────┐
│                      MySQL Database                           │
│  users, projects, tasks, subtasks, repository_entries,       │
│  workflow_messages, workflow_files, workflow_audit_logs,     │
│  workflow_notifications, etc.                                │
└─────────────────────────────────────────────────────────────┘
```

## Core Modules

| Module | Purpose | Key Tables |
|--------|---------|------------|
| **User Management** | Admin-only user provisioning, role assignment, profile photos | `users` |
| **Projects** | Project CRUD, coordinator assignment, status workflow | `projects`, `project_assignments` |
| **Tasks** | Task management within projects | `tasks` |
| **Work Items** | Subtask management, subordinate assignment, progress tracking | `subtasks`, `subtask_assignments` |
| **Communication** | Encrypted messages, file attachments | `workflow_messages`, `workflow_files` |
| **Notifications** | Real-time notifications for assignments, messages, progress | `workflow_notifications` |
| **AI Comparison** | Requirement vs deliverable analysis | `workflow_comparison_configs`, `workflow_comparison_results` |
| **Reports** | Project progress, task status, performance metrics | (queries across all tables) |
| **Repository** | Institutional preservation of finalized projects | `repository_entries`, `repository_updates` |
| **Audit Trail** | System-wide activity logging | `workflow_audit_logs` |

## Authentication Flow

```
┌──────────┐     POST /login      ┌──────────────┐     Authenticated     ┌──────────────┐
│  Login   │ ──────────────────► │  Middleware  │ ────────────────────► │  Dashboard   │
│   Page   │                     │  (auth +     │                       │  (role-based)│
│          │ ◄────────────────── │  active)     │ ◄───────────────────  │              │
└──────────   Redirect/401      ──────────────┘   Props via Inertia   └──────────────┘
```

## Authorization Model

```
─────────────────────────────────────────────────────────────┐
│                     Permission Check Flow                     │
─────────────────────────────────────────────────────────────┤
│  1. Route middleware checks permission (Spatie)               │
│  2. Controller calls $this->authorize('action', $model)       │
│  3. Policy method evaluated (e.g., ProjectPolicy::update)     │
│  4. FormRequest::authorize() as defense-in-depth              │
│  5. If all pass → action executed                             │
│  6. If any fail → 403 Forbidden                               │
└─────────────────────────────────────────────────────────────┘
```

## Data Flow Example: Project Creation

```
Admin → POST /projects
    → StoreProjectRequest validates input
    → ProjectController::authorize('create', Project)
    → ProjectPolicy::create() checks 'create project' permission
    → Project::create([...validated data])
    → RepositoryEntry auto-created (linked to project)
    → AuditLog created
    → Redirect to project show page
```

## Caching Strategy

```
─────────────────────────────────────────────────────────────┐
│                    Cache Layer (Redis)                        │
├─────────────────────────────────────────────────────────────┤
│  Dashboard data: 60s TTL                                     │
│  Reference data (departments, roles): 10-60 min TTL          │
│  Cache invalidation on: create/update/delete actions          │
│  Fallback: Database query if cache miss                       │
└─────────────────────────────────────────────────────────────┘
```

## File Storage

```
storage/
├── app/
│   ├── private/              # Private files (not web-accessible)
│   │   └── workflow-files/   # Workflow file attachments
│   └── public/
│       └── profile-photos/   # User profile photos (web-accessible)
└── ...

public/storage → symlink → storage/app/public
```

## Error Handling

| Error | Trigger | Response |
|-------|---------|----------|
| **404** | Route not found, model not found | Custom 404 page with navigation |
| **403** | Permission denied, policy fail | Custom 403 page with "Access Denied" message |
| **500** | Server error, exception | Custom 500 page with error ID (no stack trace in production) |
| **419** | CSRF token mismatch | Redirect to previous page with error message |
| **422** | Validation failure | Redirect back with field-level errors |
| **429** | Rate limit exceeded | Retry-After header + error message |
