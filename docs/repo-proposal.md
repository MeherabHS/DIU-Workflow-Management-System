# DIUS Management Portal Repository Proposal

## Goal

This repository should support a high-accountability internal management portal where every important action is traceable, reviewable, and recoverable. The repo structure should reduce mistakes by separating business rules, database changes, UI pages, and audit-sensitive workflows.

## Recommended Stack

- Backend: PHP 8.2+
- Database: MySQL 8+
- Frontend: Blade/PHP templates, Bootstrap, minimal JavaScript
- Auth: session-based authentication with strict role checks
- Storage: private local storage for uploads, public storage only for approved static assets
- Reporting: PDF and Excel export through dedicated services

## Recommended Repository Shape

This should be a single main application repository, not multiple disconnected repos. For your project stage, one well-organized monorepo is the safest choice because it keeps code, database, documentation, and process rules together.

Recommended top-level layout:

```text
dius-management-portal/
|-- app/
|-- bootstrap/
|-- config/
|-- database/
|-- docs/
|-- public/
|-- resources/
|-- routes/
|-- scripts/
|-- storage/
|-- tests/
|-- .editorconfig
|-- .env.example
|-- .gitattributes
|-- .gitignore
|-- composer.json
|-- README.md
```

## Why This Repo Design Fits Your Goal

- `app/` keeps the real business logic in one place instead of mixing it into pages.
- `database/` gives a controlled place for schema changes, seed data, and audit-safe migrations.
- `docs/` preserves theory, requirements, workflow decisions, and role rules so the system does not drift.
- `storage/` separates sensitive uploaded files from public web access.
- `tests/` protects the most important workflows from regression.

## Accountability-First Design Rules

These rules matter more than styling or speed:

1. No direct database writes from UI pages.
2. Every role-sensitive action must go through a service/action layer.
3. Every project, task, assignment, approval, rejection, and cleanup event must be logged.
4. Deletion should default to soft-delete or archive unless the data is clearly temporary.
5. Final repository records must never depend on temporary chat data remaining active.
6. Upload access and download access must be permission-checked separately.
7. Cleanup logic must archive first, then delete only approved temporary data.

## Branching Proposal

Use a simple branching model:

- `main`: stable and review-ready
- `develop`: active integration branch
- `feature/...`: one feature per branch
- `fix/...`: bug fixes
- `docs/...`: documentation-only changes

Example branch names:

- `feature/task-assignment-flow`
- `feature/internal-chat-threading`
- `feature/repository-tracking`
- `fix/file-upload-validation`
- `docs/srs-first-draft`

## Commit Proposal

Use small, readable commits. Suggested style:

- `feat: add coordinator to subordinate assignment flow`
- `fix: block unsafe upload extensions`
- `docs: define project cleanup retention rules`
- `test: cover task approval workflow`

## Protection Priorities

The first things this repo must protect are:

- assignment accountability
- role boundaries
- file security
- approval history
- repository history
- cleanup policy correctness

## Required Documentation Inside Repo

At minimum, keep these documents under `docs/`:

- `vision-and-scope.md`
- `roles-and-permissions.md`
- `workflow-lifecycle.md`
- `repository-rules.md`
- `retention-and-cleanup-policy.md`
- `audit-log-rules.md`
- `use-case-table.md`
- `database-notes.md`

## Testing Priorities

If you want this project to help your career, the testing story must be part of the repo proposal. Focus tests on the workflows that create trust:

1. Manager assigns project to coordinator
2. Coordinator assigns sub-task to subordinate
3. User uploads approved file type
4. Unauthorized user is blocked from file access
5. Task submission creates activity log entry
6. Approval or rejection updates audit trail
7. Completed project cleanup archives only temporary data
8. Final repository record remains visible after cleanup

## Final Recommendation

Build this as a clean, documentation-heavy, accountability-first web application repository. That will make it stronger for both submission and career value because it shows you can think beyond features and design for control, traceability, and long-term maintainability.
