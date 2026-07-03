# DIUS Workflow Management Portal - Technical Documentation

This document is the operational and developer manual for the DIUS Workflow Management Portal. It covers system architecture, setup, application data, roles, database schema, deployment, monitoring, troubleshooting, and what to do when the application breaks.

## 1. System Purpose

The application manages internal project execution and preservation workflows.

Primary workflow:

```text
Project
  -> Task
     -> Work Item
        -> Progress update
        -> Evidence upload
        -> Messages
        -> Notifications
  -> Repository preservation
  -> Reports
  -> Audit trail
```

The UI uses `Work Item`. The backend still uses `Subtask` in models, tables, and route names for compatibility.

## 2. Stack

| Area | Implementation |
| --- | --- |
| Backend framework | Laravel 13.x |
| PHP runtime | PHP 8.4 in Docker, composer allows PHP 8.3+ |
| Frontend | React 18, TypeScript, Inertia React |
| Build tool | Vite |
| Styling | Tailwind CSS |
| Authorization | Laravel policies plus Spatie Laravel Permission |
| Database | MySQL default, PostgreSQL extension installed in Docker |
| File storage | Laravel local/private disk for workflow files, public disk for profile photos |
| Production web server | Nginx |
| PHP process manager | PHP-FPM |
| Process supervisor | Supervisor |
| Testing | PHPUnit, TypeScript build |

## 3. Repository Structure

```text
app/
  Console/Commands/              Scheduled or manual maintenance commands
  Helpers/                       Shared helper classes
  Http/Controllers/              Laravel request handlers
  Http/Middleware/               Inertia and access middleware
  Http/Requests/                 Form request validation
  Models/                        Eloquent models
  Observers/                     Project observer for repository sync
  Policies/                      Authorization policies
  Services/                      Domain services
  Support/ProjectStatus.php      Central status helper
bootstrap/app.php                Laravel 11+ style bootstrap configuration
config/                          Laravel configuration
routes/web.php                   Main web routes
routes/auth.php                  Auth routes
database/migrations/             Database schema
database/seeders/                Role and demo-user seeding
resources/js/                    Inertia React frontend
resources/views/app.blade.php    Inertia root shell
public/                          Public web root
docker/                          Nginx, PHP, Supervisor config
Dockerfile                       Production container image
start.sh                         Runtime startup script
tests/                           Feature and unit tests
```

## 4. Runtime Architecture

Production Docker runtime:

```text
External request
  v
Nginx on ${PORT:-8080}
  |-- serves /app/public and /app/public/build
  |-- rejects hidden/internal paths
  |-- enforces client_max_body_size 150M
  v
PHP-FPM on 127.0.0.1:9000
  v
Laravel application
  v
Database + storage
```

Supervisor starts and monitors both PHP-FPM and Nginx.

Important files:

- `Dockerfile`
- `start.sh`
- `docker/nginx/default.conf.template`
- `docker/supervisor/supervisord.conf`
- `docker/php/uploads.ini`

## 5. Environment Variables

Start from `.env.example`.

Required for local development:

```env
APP_NAME="DIUS Management Portal"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dius_management_portal
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning
```

Production expectations:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning
SENTRY_LARAVEL_DSN=
SENTRY_TRACES_SAMPLE_RATE=0
```

Do not commit `.env`.

## 6. Local Setup

### 6.1 Prerequisites

- PHP 8.3 or newer
- Composer
- Node.js 22 recommended
- npm
- MySQL or PostgreSQL
- Git

### 6.2 Install

```powershell
composer install
npm install
copy .env.example .env
php artisan key:generate
```

Update database settings in `.env`, then run:

```powershell
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan storage:link
npm run build
```

For local development:

```powershell
php artisan serve
npm run dev
```

For a production-like local build:

```powershell
npm run build
php artisan optimize:clear
php artisan test
```

## 7. Seeded Users

Seeded users use password `password`.

| Email | Role |
| --- | --- |
| `admin@example.com` | Admin |
| `pm@example.com` | PM/Manager |
| `coordinator@example.com` | Coordinator |
| `subordinate@example.com` | Subordinate |

Seeder:

```text
database/seeders/RolePermissionSeeder.php
```

## 8. Roles And Permissions

### Admin

- Access admin dashboard.
- Manage users.
- Assign roles.
- View audit trail.
- View and export reports.
- Full project, repository, workflow message, and workflow file access.

### PM/Manager

- Access PM dashboard.
- Create and update projects.
- Assign Coordinators.
- Manage tasks and Work Items within allowed project scope.
- View repository and reports if permission is assigned.

### Coordinator

- Access Coordinator dashboard.
- View assigned projects.
- Create and update tasks and Work Items for assigned projects.
- Assign and revoke Subordinates on Work Items.
- Upload and download workflow files for assigned scope.

### Subordinate

- Access Subordinate dashboard.
- View My Work Items.
- Update assigned Work Item progress.
- Upload evidence files.
- Send workflow messages within assigned scope.

## 9. Main Application Features

### Authentication And User Management

- Breeze-based auth screens through Inertia React.
- Pending users can register but cannot fully access the app until role assignment.
- Admin user management includes active status, role, department, designation, phone, password reset token generation, and profile photo management.

### Dashboards

- Role-specific dashboard routes:
  - `/admin/dashboard`
  - `/pm/dashboard`
  - `/coordinator/dashboard`
  - `/subordinate/dashboard`
- Shared `/dashboard` redirects or renders based on role.
- KPI labels use current terminology such as Total Projects and Work Items.

### Project Management

Project statuses:

```text
planned
in_progress
submitted
completed
archived
cancelled
```

Legacy project values:

```text
active -> displayed as In Progress
archive_pending -> normalized/displayed as Completed where legacy fallback is needed
```

### Task And Work Item Management

Task and Work Item statuses include:

```text
pending
in_progress
submitted
approved
revision_required
completed
cancelled
```

Subordinates update assigned Work Items through the My Work Items area.

### Repository Preservation

Repository statuses:

```text
planned
ongoing
submitted
completed
archived
cancelled
```

Important behavior:

- Project creation auto-preserves a repository entry.
- Project status updates sync to repository status unless the repository entry has already been finalized.
- `in_progress` project status maps to repository `ongoing`.
- Repository data is treated as institutional preservation data, not only a final archive.

### Workflow Files

Workflow files are private by default.

Allowed workflow file extensions:

```text
pdf, doc, docx, xls, xlsx, ppt, pptx, zip, png, jpg, jpeg, webp, txt, csv
```

Workflow file limit:

```text
100 MB / 102400 KB
```

Storage behavior:

```text
Disk: local
Path pattern: workflow-files/YYYY/MM/{uuid}.{extension}
Download: authorized Laravel route only
Route: /workflow-files/{workflowFile}/download
```

Profile photos are separate:

```text
Allowed: jpg, jpeg, png, webp
Limit: 2 MB / 2048 KB
Disk: public
Path: profile-photos
```

### Messages

Workflow messages exist at project, task, and Work Item levels.

Message body is stored by the backend and rendered only to authorized users. Message routes are scoped through policies.

### Notifications

Notifications are used for:

- Coordinator assignment and revocation.
- Subordinate assignment and revocation.
- Workflow messages.
- Workflow file uploads.
- Deadline reminders.

Action URLs are relative paths, not hardcoded localhost URLs.

### Reports

Reports include:

- Project Progress
- Task / Work Item Status
- Coordinator Performance
- Subordinate Work Completion
- Repository Preservation
- Audit Activity

CSV export is permission-gated and rate-limited.

### AI Requirement/Deliverable Comparison

AI comparison is optional and controlled by environment variables.

```env
AI_COMPARISON_ENABLED=false
AI_PROVIDER=openai_compatible
AI_API_KEY=
AI_BASE_URL=https://api.openai.com/v1
AI_MODEL=gpt-4o-mini
```

If disabled or unconfigured, the app should remain usable.

## 10. Database Schema Summary

This is a high-level schema map. Use `database/migrations` for the exact source of truth.

### Identity And Access

```text
users
  id
  name
  email
  email_verified_at
  profile_photo_path
  is_active
  department_id
  designation
  phone
  password
  remember_token
  timestamps

roles, permissions, model_has_roles, model_has_permissions, role_has_permissions
  provided by Spatie Laravel Permission

password_reset_tokens
sessions
cache, cache_locks
jobs, job_batches, failed_jobs
```

### Organization And Workflow

```text
departments
  id, name, code, description, is_active, timestamps, softDeletes

projects
  id, title, description, department_id, created_by
  status, priority, start_date, deadline
  completed_at, archived_at, timestamps, softDeletes

project_assignments
  id, project_id, coordinator_id, assigned_by
  assignment_role, assigned_at, revoked_at, timestamps

tasks
  id, project_id, title, description, created_by, assigned_to
  status, priority, deadline, submitted_at, approved_at
  timestamps, softDeletes

subtasks
  id, project_id, task_id, title, description, created_by
  status, priority, deadline, submitted_at, approved_at
  progress_note, timestamps, softDeletes

subtask_assignments
  id, subtask_id, subordinate_id, assigned_by
  assigned_at, revoked_at, timestamps
```

### Repository

```text
repository_entries
  id, project_id, title, type, department_id, client_or_office
  responsible_user_id, status, deadline, value_amount, value_currency
  description, final_summary, submitted_at, completed_at, archived_at
  created_by, finalized_at, finalized_by, final_status_snapshot
  timestamps, softDeletes

repository_updates
  id, repository_entry_id, user_id
  update_type, old_status, new_status, note, timestamps
```

### Workflow Communication And Files

```text
workflow_messages
  id, project_id, task_id, subtask_id, sender_id
  message_type, body, visibility, timestamps, softDeletes

workflow_files
  id, project_id, task_id, subtask_id, repository_entry_id
  uploaded_by, original_name, stored_name, disk, path
  mime_type, size, file_category, description
  timestamps, softDeletes

workflow_notifications
  id, user_id, actor_id, project_id, task_id, subtask_id
  workflow_message_id, workflow_file_id
  type, title, body, action_url, read_at, timestamps

workflow_audit_logs
  id, actor_id, action, entity_type, entity_id
  project_id, task_id, subtask_id, repository_entry_id
  ip_address, user_agent, metadata, created_at
```

### AI Comparison

```text
workflow_comparison_configs
  id, project_id, task_id, subtask_id, enabled, timestamps

workflow_requirements
  id, comparison_config_id, workflow_file_id, requirement_text, source_page, timestamps

workflow_deliverables
  id, comparison_config_id, workflow_file_id, deliverable_text, source_page, timestamps

workflow_comparison_results
  id, comparison_config_id, requirement_id, deliverable_id
  status, matched_items, completion_percentage, summary, error_message, timestamps
```

### Legacy Tables

The `messages`, `files`, and `archive_records` tables exist for compatibility/legacy paths. Do not delete them without a dedicated migration and compatibility plan.

## 11. HTTP Route Surface

Public:

```text
GET /health
GET /
```

Auth:

```text
/register
/login
/forgot-password
/reset-password/{token}
/verify-email
/confirm-password
/logout
```

Authenticated application groups include:

```text
/dashboard
/admin/dashboard
/pm/dashboard
/coordinator/dashboard
/subordinate/dashboard
/projects
/projects/{project}/tasks
/tasks/{task}
/tasks/{task}/subtasks
/subtasks/{subtask}
/my-work-items
/my-subtasks
/repository
/profile
/notifications
/reports
/admin/users
/admin/audit-logs
```

File routes:

```text
/projects/{project}/files
/tasks/{task}/files
/subtasks/{subtask}/files
/repository/{repositoryEntry}/files
/workflow-files/{workflowFile}/download
```

Message routes:

```text
/projects/{project}/messages
/tasks/{task}/messages
/subtasks/{subtask}/messages
```

## 12. Rate Limits

Named Laravel limiters are configured in `AppServiceProvider`.

| Limiter | Limit | Key |
| --- | --- | --- |
| login | 5/minute | lowercase email + IP |
| register | 3/hour and 10/day | IP |
| password-reset | 3/15 minutes | lowercase email + IP |
| confirm-password | 6/minute | user id or IP |
| ai-comparison | 3/minute | user id |
| workflow-upload | 10/10 minutes | user id |
| profile-photo | 5/10 minutes | user id |
| workflow-message | 20/minute | user id |
| notification-action | 60/minute single read, 10/minute read-all | user id |
| report-export | 5 CSV exports/10 minutes | user id |

## 13. Logging And Monitoring

Logging defaults:

```env
LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning
```

Health check:

```text
GET /health
```

Healthy response returns HTTP 200. Failed database or storage checks return HTTP 503.

Recommended external monitoring:

```text
Uptime monitor -> GET /health every 1-5 minutes -> alert by email, Slack, Telegram, or incident tool
```

Alert conditions:

- `/health` returns non-200.
- App does not respond.
- Repeated 500 errors.
- Database unavailable.
- Storage permission errors.
- Queue failures if queue workers are enabled.
- High failed-login or throttling rate.

## 14. Deployment

### 14.1 Docker Build

```powershell
docker build -t dius-portal-nginx-fpm .
```

### 14.2 Docker Run

```powershell
docker run --rm -p 8080:8080 --env-file .env dius-portal-nginx-fpm
```

Check:

```text
http://localhost:8080
http://localhost:8080/login
http://localhost:8080/health
```

### 14.3 Render

Render should build the Dockerfile and provide `$PORT`. `start.sh` renders the Nginx config to listen on that port.

Startup sequence:

```text
export PORT=${PORT:-8080}
envsubst Nginx template
php artisan config:clear
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan storage:link
php artisan optimize:clear
supervisord -> php-fpm + nginx
```

Render storage warning:

- Profile photos use public disk.
- Workflow files use private local disk.
- Configure Render Persistent Disk or object storage for production persistence.

### 14.4 DigitalOcean

Docker-based deployment can use the same image.

Non-Docker Droplet equivalent:

```text
Ubuntu
Nginx
PHP 8.4-FPM
MySQL or PostgreSQL
Supervisor
Certbot SSL
client_max_body_size 150M
PHP upload ini values from docker/php/uploads.ini
```

## 15. Upload Limits

Laravel workflow file validation:

```text
max:102400 KB = 100 MB
```

PHP/container limits:

```ini
upload_max_filesize=120M
post_max_size=150M
memory_limit=512M
max_execution_time=300
max_input_time=300
```

Nginx:

```nginx
client_max_body_size 150M;
```

Profile photo limit remains 2 MB.

## 16. Operational Runbook: What To Do When The App Breaks

### 16.1 First Checks

1. Open `/health`.
2. Check Render or Docker logs.
3. Check Laravel logs.
4. Confirm database connectivity.
5. Confirm storage is writable.
6. Confirm latest deployment completed asset build.
7. Confirm migrations ran.

Commands:

```powershell
php artisan optimize:clear
php artisan route:list
php artisan migrate:status
php artisan test --filter=HealthCheckTest
```

### 16.2 If `/health` Returns 503

Read the `checks` object.

Database failed:

```text
- Verify DB host, port, database, username, password.
- Verify database server is running.
- Verify network/firewall access from app host to DB.
- Run php artisan migrate:status.
```

Storage failed:

```text
- Verify storage directory exists.
- Verify bootstrap/cache exists.
- Verify permissions: storage and bootstrap/cache writable by web user.
- In Docker, verify chown to www-data happened.
```

### 16.3 If Login Breaks

Check:

```text
- storage/logs/laravel.log
- APP_KEY exists and is valid
- SESSION_DRIVER table exists if using database sessions
- cache table exists if CACHE_STORE=database
- roles are seeded
- user is active
- user has a role
```

Commands:

```powershell
php artisan optimize:clear
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan permission:cache-reset
php artisan test --filter=AuthenticationTest
```

### 16.4 If Frontend Is Blank

Check:

```text
- public/build/manifest.json exists
- npm run build completed
- Vite assets return 200 in browser Network tab
- Browser console for runtime error
- Laravel logs for Inertia rendering error
```

Commands:

```powershell
npm run build
php artisan view:clear
php artisan optimize:clear
```

The app includes a loading fallback and React error boundary. Production users should not see raw stack traces.

### 16.5 If Uploads Fail

Workflow file uploads:

```text
- Confirm file is <= 100 MB.
- Confirm extension is in allowed workflow list.
- Confirm PHP limits are above 100 MB.
- Confirm Nginx client_max_body_size is 150M.
- Confirm storage/app/private exists and is writable.
- Confirm user has upload permission for that context.
```

Profile photo uploads:

```text
- Confirm file is <= 2 MB.
- Confirm type is jpg, jpeg, png, or webp.
- Confirm storage:link exists for public files.
```

### 16.6 If Notifications Do Not Appear

Check:

```text
- workflow_notifications table
- recipient user_id
- action_url is relative
- user has permission to open target URL
- notification bell props through Inertia shared data
```

Run:

```powershell
php artisan test --filter=WorkflowNotificationTest
```

### 16.7 If Repository Data Looks Wrong

Check:

```text
- ProjectObserver is registered in AppServiceProvider.
- ProjectStatus::repositoryStatusForProjectStatus maps in_progress to ongoing.
- finalized_at is not null for finalized entries that should not sync further.
- BackfillRepositoryEntries command is idempotent.
```

Run:

```powershell
php artisan test --filter=RepositoryModuleTest
```

### 16.8 If Reports Fail

Check:

```text
- user has view reports/export reports permission
- database query errors in logs
- CSV export rate limit
- report service scoped project IDs
```

Run:

```powershell
php artisan test --filter=WorkflowReportsTest
```

### 16.9 If Deployment Fails

Check:

```text
- Docker build logs
- composer install layer
- npm install layer
- npm run build layer
- php artisan package:discover
- startup migrations
- Nginx config rendered from template
- Supervisor started php-fpm and nginx
```

Inside a built container:

```sh
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit|max_execution_time|max_input_time"
ps aux
nginx -t
```

Expected no production `php artisan serve` process.

## 17. Test Plan

Common checks before handover:

```powershell
npm run build
php artisan optimize:clear
php artisan test --filter=AuthenticationTest
php artisan test --filter=SecurityTest
php artisan test --filter=HealthCheckTest
php artisan test --filter=RateLimitTest
php artisan test --filter=WorkflowFileTest
php artisan test --filter=WorkflowMessageTest
php artisan test --filter=WorkflowNotificationTest
php artisan test --filter=RepositoryModuleTest
php artisan test --filter=WorkflowReportsTest
php artisan test
```

The latest full run completed with:

```text
369 tests, 2888 assertions
```

## 18. Development Rules

- Do not rename `Subtask` model/table/routes casually. UI may say Work Item, backend compatibility still depends on Subtask names.
- Do not expose private workflow files through `/storage` URLs.
- Do not change profile photo validation when working on workflow files.
- Do not change repository status values without updating `ProjectStatus` and tests.
- Do not change roles or permissions without updating `RolePermissionSeeder` and role boundary tests.
- Do not remove legacy tables without a dedicated migration plan.
- Keep business logic in Laravel controllers, services, policies, observers, and requests. React should render and submit state, not become the source of truth.
- For deployment changes, do not fall back to `php artisan serve` in production.

## 19. Useful Commands

```powershell
php artisan route:list
php artisan optimize:clear
php artisan storage:link
php artisan permission:cache-reset
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan test
npm run build
```

Docker:

```powershell
docker build -t dius-portal-nginx-fpm .
docker run --rm -p 8080:8080 --env-file .env dius-portal-nginx-fpm
```

## 20. Known Operational Notes

- Local Docker storage is not durable unless a persistent volume is mounted.
- Render free/prototype services may sleep and take time to wake.
- Use `/health` for uptime monitoring instead of waiting for client reports.
- `APP_DEBUG=false` is mandatory in production.
- Use external object storage or persistent disk before relying on uploaded files in production.
- Optional Sentry or Flare integration is documented but not required to run the app.
