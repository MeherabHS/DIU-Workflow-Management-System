---
name: Application Architecture Overview
description: DIUS Management Portal - Laravel + React/Inertia project management system with role-based access control
type: project
---

## Application Purpose
DIUS = Project, Communication & Repository Management Portal. Institutional workflow management system for managing projects, tasks, repository records, and team coordination. Default currency is BDT (Bangladeshi Taka), suggesting Bangladeshi institutional use.

## Tech Stack
- **Backend:** Laravel 12 (PHP), MySQL
- **Frontend:** React 18 + TypeScript + Inertia.js + Tailwind CSS + Vite
- **Auth:** Laravel Breeze
- **RBAC:** spatie/laravel-permission (4 roles, 30 permissions)

## Core Domain Model
Hierarchical workflow:
```
Department ──< Project >── ProjectAssignment >── User (Coordinator)
                   ├──< Task >──< Subtask >── SubtaskAssignment >── User (Subordinate)
                   ├──< RepositoryEntry >──< RepositoryUpdate
                   ├──< WorkflowMessage (threaded communication)
                   └──< WorkflowFile (attachments)
```

## Key Models (14 total)
- **User** - Has Spatie HasRoles trait, relationships to projects/tasks/subtasks
- **Department** - Organizational units
- **Project** - Top-level work unit (soft deletes), has coordinators via ProjectAssignment
- **Task** - Work under Project (soft deletes)
- **Subtask** - Granular work under Task (soft deletes), called "Work Items"
- **RepositoryEntry** - Permanent institutional records (soft deletes)
- **RepositoryUpdate** - Timeline/status logs for RepositoryEntries
- **WorkflowMessage** - Threaded communication (message/feedback/follow_up/progress_note/clarification)
- **WorkflowFile** - File attachments at any workflow level (soft deletes)
- **ProjectAssignment** - Junction: Project <-> User (Coordinator), tracks assignment_role, revoked_at
- **SubtaskAssignment** - Junction: Subtask <-> User (Subordinate), tracks revoked_at
- **Message** - Older direct messaging system (legacy)
- **File** - Older file system (legacy)
- **ArchiveRecord** - Audit log for archived items

## Roles & Permissions
4 roles: Admin, PM/Manager, Coordinator, Subordinate
30 granular permissions controlling dashboard access and CRUD operations

## Controllers (13 total)
DashboardController, ProjectController, TaskController, SubtaskController, SubtaskAssignmentController, MySubtaskController, RepositoryController, WorkflowFileController, WorkflowMessageController, ProfileController, Auth controllers

## Controller Concerns (Traits)
- **ProvidesWorkflowFiles** - Shared file upload/listing logic
- **ProvidesWorkflowMessages** - Shared message threading logic

## Key Patterns
- Soft deletes on most domain models
- Assignment audit trail (assigned_by, assigned_at, revoked_at)
- Dual file/message systems (legacy Message/File vs newer WorkflowMessage/WorkflowFile)
- Role-specific dashboards
- Form Requests for validation (12 typed validators)
- Policies for authorization (5 policies with isAssignedCoordinator/isAssignedSubordinate checks)

## Testing
- PHPUnit 12.5, 23 test files, 147 tests, 1002 assertions, all passing
- Test DB: dius_management_portal_testing (MySQL)

## Routes
No routes/api.php - all routes are web routes with JSON API endpoints for files
Routes wrapped in ['auth', 'verified'] middleware
Permission middleware for route-level access control

## Seed Users (password: "password")
- admin@example.com
- pm@example.com
- coordinator@example.com
- subordinate@example.com