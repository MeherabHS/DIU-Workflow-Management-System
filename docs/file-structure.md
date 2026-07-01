# DIUS Management Portal File Structure Proposal

## Purpose

This structure is designed to reduce mistakes by making each responsibility obvious. The project should keep role logic, workflow logic, uploaded files, audit records, and documentation clearly separated.

## Proposed Structure

```text
dius-management-portal/
|-- app/
|   |-- Actions/
|   |   |-- Assignments/
|   |   |-- Chat/
|   |   |-- Cleanup/
|   |   |-- Projects/
|   |   |-- Reports/
|   |   `-- Tasks/
|   |-- Http/
|   |   |-- Controllers/
|   |   |-- Middleware/
|   |   `-- Requests/
|   |-- Models/
|   |-- Notifications/
|   |-- Policies/
|   |-- Repositories/
|   |-- Services/
|   `-- Support/
|-- bootstrap/
|-- config/
|   |-- app.php
|   |-- auth.php
|   |-- files.php
|   |-- permissions.php
|   |-- repository.php
|   `-- retention.php
|-- database/
|   |-- factories/
|   |-- migrations/
|   `-- seeders/
|-- docs/
|   |-- audit-log-rules.md
|   |-- database-notes.md
|   |-- file-structure.md
|   |-- repo-proposal.md
|   |-- repository-rules.md
|   |-- retention-and-cleanup-policy.md
|   |-- roles-and-permissions.md
|   |-- use-case-table.md
|   |-- vision-and-scope.md
|   `-- workflow-lifecycle.md
|-- public/
|   |-- assets/
|   |-- css/
|   |-- js/
|   `-- index.php
|-- resources/
|   |-- views/
|   |   |-- auth/
|   |   |-- dashboards/
|   |   |-- messages/
|   |   |-- projects/
|   |   |-- repository/
|   |   |-- reports/
|   |   `-- tasks/
|   `-- templates/
|-- routes/
|   |-- web.php
|   |-- auth.php
|   |-- projects.php
|   |-- tasks.php
|   |-- messages.php
|   |-- repository.php
|   `-- reports.php
|-- scripts/
|   |-- cleanup-completed-projects.php
|   |-- nightly-reminders.php
|   `-- repository-rebuild.php
|-- storage/
|   |-- app/
|   |   |-- private/
|   |   |   |-- chat-attachments/
|   |   |   |-- final-reports/
|   |   |   |-- project-files/
|   |   |   `-- repository-documents/
|   |   `-- archive/
|   |       |-- chats/
|   |       |-- followups/
|   |       `-- temp-files/
|   |-- logs/
|   `-- framework/
|-- tests/
|   |-- Feature/
|   |   |-- Assignments/
|   |   |-- Cleanup/
|   |   |-- Files/
|   |   |-- Messages/
|   |   |-- Repository/
|   |   `-- Tasks/
|   `-- Unit/
|-- .editorconfig
|-- .env.example
|-- .gitattributes
|-- .gitignore
|-- composer.json
`-- README.md
```

## Folder Responsibility

### `app/Actions/`

Put workflow-critical operations here:

- assign project
- create task
- forward task
- submit work
- approve work
- reject work
- archive temporary project data

This is where accountability logic should live, not inside controllers.

### `app/Policies/`

This is where role protection should be enforced:

- who can assign
- who can approve
- who can download
- who can archive
- who can restore

### `app/Repositories/`

Use this for data access rules where project history and repository views need careful query control.

### `config/retention.php`

Keep cleanup policy visible and centralized:

- active follow-up retention days
- archive rules
- deletable data types
- permanent data types

### `scripts/`

Use scheduled scripts for:

- deadline reminders
- overdue alerts
- 14-day cleanup processing
- repository maintenance jobs

### `storage/app/private/`

All sensitive project files should stay outside direct public access.

### `storage/app/archive/`

This is critical for your requirement. Temporary chat and follow-up material can move here after project completion while final records stay preserved elsewhere.

## Non-Negotiable Records To Preserve

These should remain even after cleanup:

- project metadata
- task metadata
- assignment history
- final reports
- approval and rejection decisions
- checklist comparison results
- repository timeline
- activity logs

## Safe-To-Archive Or Remove Later

These can be archived after 14 days:

- temporary chat attachments
- low-value follow-up chat history
- draft-only supporting files
- transient reminder notifications

## Suggested README Sections

Your `README.md` should include:

1. Project purpose
2. Core modules
3. User roles
4. Accountability model
5. Cleanup and retention logic
6. Local setup
7. Test strategy

## Career Value Angle

This structure makes the project stronger for your future because it shows:

- system thinking
- secure file handling awareness
- role-based workflow design
- archive and retention planning
- auditability mindset
- scalable documentation habits

That is the difference between a normal student CRUD project and a serious portfolio project.
