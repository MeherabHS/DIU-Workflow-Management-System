# DIUS Workflow Management Portal

A production-oriented Laravel and Inertia React workflow management portal built for role-based project delivery, internal communication, file evidence, repository preservation, auditability, and deployment readiness.

The application models an organizational workflow where Admins and PMs create projects, Coordinators manage execution, and Subordinates complete assigned Work Items with messages, progress updates, and evidence uploads.

## Portfolio Summary

This project is a full-stack management system built around a real workflow domain rather than a static CRUD demo. It includes authentication, role boundaries, dashboards, repository preservation, private file storage, notifications, audit logs, reports, operational health checks, rate limiting, and a production Docker runtime using Nginx and PHP-FPM.

The project demonstrates practical backend engineering, frontend integration, deployment hardening, and production-readiness decisions.

## Core Capabilities

- Role-based dashboards for Admin, PM/Manager, Coordinator, and Subordinate users.
- Project -> Task -> Work Item hierarchy.
- Coordinator assignment at project level.
- Subordinate assignment at Work Item level.
- My Work Items workspace for Subordinates.
- Workflow messages for project, task, and Work Item communication.
- Private workflow file upload and authorized download routes.
- Repository Tracker that preserves project records and timeline updates.
- Notifications for assignments, messages, file uploads, deadline alerts, and workflow events.
- Reports and CSV export for project progress, task status, coordinator performance, subordinate completion, repository preservation, and audit activity.
- User management with roles, active/pending status, department, designation, phone, and profile photos.
- Audit logs for user and workflow activity.
- Health endpoint for uptime monitoring.
- Rate limiting for login, registration, password reset, file upload, profile photo upload, messages, notifications, AI comparison, and report export.
- Nginx + PHP-FPM Docker runtime suitable for Render, DigitalOcean App Platform, or Docker-based hosts.

## Architecture

```text
Browser
  |
  | HTTP
  v
Nginx
  |-- serves static assets from /public/build
  |-- routes PHP requests to PHP-FPM
  v
PHP-FPM
  v
Laravel
  |-- routes/web.php
  |-- controllers, policies, form requests
  |-- services for files, reports, notifications, audit logs, AI comparison
  v
Database + Storage
  |-- MySQL or PostgreSQL-compatible database
  |-- private workflow files in storage/app/private workflow paths
  |-- public profile photos through Laravel public storage link
```

## Application Workflow

```text
Admin / PM
  |
  | creates project
  v
Project
  |
  | assigns Coordinator
  v
Coordinator
  |
  | creates tasks and Work Items
  v
Task
  |
  | contains
  v
Work Item
  |
  | assigned to Subordinate
  v
Subordinate
  |
  | updates progress, uploads evidence, sends messages
  v
Repository + Reports + Audit Trail
```

## Role Model

| Role | Main Access |
| --- | --- |
| Admin | Full oversight, users, audit trail, reports, projects, repository |
| PM/Manager | Project management, coordinator assignment, reports, repository |
| Coordinator | Assigned projects, tasks, Work Items, subordinate assignment |
| Subordinate | My Work Items, progress updates, evidence upload, messages |

The frontend uses client-facing terminology `Work Item`. The backend still uses `Subtask` model, table, and route names for compatibility.

## Technology Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.4 runtime, Laravel 13.x, Laravel Breeze, Inertia Laravel |
| Frontend | React 18, TypeScript, Inertia React, Vite, Tailwind CSS |
| Authorization | Spatie Laravel Permission plus Laravel policies |
| Database | MySQL by default, PostgreSQL driver installed in Docker |
| Files | Laravel local/private disk for workflow files, public disk for profile photos |
| Runtime | Docker, Nginx, PHP-FPM, Supervisor |
| Observability | Laravel daily logs, stderr logs, `/health` endpoint |
| Testing | PHPUnit feature/unit tests, TypeScript build check |

## Data Model Overview

```text
users
  |-- roles and permissions through Spatie tables
  |-- projects.created_by
  |-- project_assignments.coordinator_id
  |-- subtask_assignments.subordinate_id
  |-- workflow_files.uploaded_by
  |-- workflow_notifications.user_id

projects
  |-- tasks
  |-- subtasks / Work Items
  |-- repository_entries
  |-- workflow_messages
  |-- workflow_files
  |-- workflow_audit_logs

tasks
  |-- subtasks / Work Items
  |-- workflow_messages
  |-- workflow_files

subtasks
  |-- subtask_assignments
  |-- workflow_messages
  |-- workflow_files

repository_entries
  |-- repository_updates
  |-- workflow_files
```

## Production Runtime

The Docker runtime no longer uses `php artisan serve`. Production traffic flows through Nginx and PHP-FPM:

```text
Nginx : ${PORT:-8080}
  -> /app/public
  -> FastCGI 127.0.0.1:9000
  -> PHP-FPM
```

Important runtime files:

- `Dockerfile`
- `start.sh`
- `docker/nginx/default.conf.template`
- `docker/supervisor/supervisord.conf`
- `docker/php/uploads.ini`

Workflow uploads support 100 MB at Laravel validation level. PHP and Nginx limits are higher so Laravel can return controlled validation responses.

## Health And Monitoring

The public health endpoint is:

```text
GET /health
```

It checks:

- Laravel app boot
- database connectivity through a lightweight `select 1`
- required storage directories are readable and writable

Healthy response:

```json
{
  "status": "ok",
  "app": "DIUS Workflow Management Portal",
  "environment": "production",
  "checks": {
    "database": "ok",
    "storage": "ok"
  }
}
```

Use an external uptime monitor such as UptimeRobot, Better Stack, Healthchecks, or a cloud monitor to call `/health` every 1 to 5 minutes.

## What Has Been Done

- Built Laravel + Inertia React application shell.
- Implemented role-specific dashboards and permission-aware navigation.
- Implemented Project -> Task -> Work Item workflow.
- Added coordinator and subordinate assignment flows.
- Added private workflow file upload/download with authorization.
- Added repository preservation workflow and status synchronization.
- Added notifications, message threads, deadline reminders, and relative action URLs.
- Added report pages and CSV exports.
- Added profile photo upload with initials fallback.
- Added frontend loading screen and React error boundary.
- Added centralized project status helper.
- Added centralized workflow file service.
- Added rate limiting for security and stability.
- Added `/health`, daily/stderr logging, and operational failure logs.
- Replaced production `php artisan serve` with Nginx + PHP-FPM + Supervisor.
- Added broad feature tests for workflow, security, notifications, repository, files, reports, rate limits, and health checks.

## What I Learned

- How to structure a Laravel + Inertia application where Laravel remains the source of truth and React handles presentation.
- How to enforce business boundaries with policies, roles, permissions, middleware, and route-level checks.
- How to separate user audit logs from operational application logs.
- How to design private file storage so sensitive workflow files are downloaded only through authorized controller routes.
- How to protect production upload flows with Laravel validation, PHP upload limits, and Nginx body-size limits.
- How to harden authentication and high-cost endpoints with practical rate limits.
- How to add health checks and deployment logs so failures can be detected before users report them.
- Why production Laravel deployments should use Nginx + PHP-FPM instead of `php artisan serve`.
- How to preserve legacy internal names while presenting cleaner business terminology in the UI.
- How to keep refactors safe by writing focused tests before and after changes.

## Local Setup Skeleton

See [documentation.md](documentation.md) for the full setup guide. The short version is:

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan storage:link
npm run build
php artisan serve
```

Default seeded users use password `password`:

| Email | Role |
| --- | --- |
| `admin@example.com` | Admin |
| `pm@example.com` | PM/Manager |
| `coordinator@example.com` | Coordinator |
| `subordinate@example.com` | Subordinate |

## Docker Deployment Skeleton

```powershell
docker build -t dius-portal-nginx-fpm .
docker run --rm -p 8080:8080 --env-file .env dius-portal-nginx-fpm
```

Then check:

```text
http://localhost:8080
http://localhost:8080/login
http://localhost:8080/health
```

## Verification

```powershell
npm run build
php artisan optimize:clear
php artisan test
```

The latest full suite run passed:

```text
369 tests, 2888 assertions
```

## Documentation

The full technical and operational manual is in [documentation.md](documentation.md). It includes environment setup, database schema, deployment, monitoring, recovery steps, rate limits, upload limits, user roles, and troubleshooting procedures.
