# DIUS Management Portal Audit Log Rules

## File: `docs/audit-log-rules.md`

## Purpose

This document defines what events must be traceable in the system and what minimum information each audit record should preserve.

## Audit Goal

The audit trail must allow authorized reviewers to reconstruct:

- who performed an action
- what action happened
- what record was affected
- when it happened
- whether the action succeeded, failed, or was blocked

## Audit Principles

- all critical business actions must be logged
- permission failures must be logged
- cleanup actions must be logged
- logs should be structured and filterable
- logs should support review without depending on temporary chat content

## Minimum Audit Fields

| Field | Purpose |
|---|---|
| user_id | Who performed the action, if authenticated |
| action | What happened |
| module | Project, task, file, message, repository, auth, cleanup, or user management |
| record_id | Related record identifier where applicable |
| old_value | Previous value summary where relevant |
| new_value | New value summary where relevant |
| status | Success, failure, blocked, archived, or similar |
| ip_address | Security context |
| user_agent | Device or browser context |
| created_at | Timestamp |

## Mandatory Audit Events

### User and Access Events

- user created
- user approved
- user blocked
- user role changed
- permission changed later if implemented
- unauthorized access attempt

### Project Events

- project created
- project updated
- project assignment created
- project assignment changed
- project deadline changed
- project marked completed

### Task and Subtask Events

- task created
- subtask created
- subordinate assigned
- assignment changed
- task forwarded
- progress updated
- work submitted
- work approved
- work rejected
- revision requested

### Communication Events

- message sent
- message archived
- attachment added to message
- unauthorized thread access attempt

### File Events

- file uploaded
- file downloaded
- file deleted or soft-deleted
- file archived
- unsafe upload attempt
- unauthorized download attempt

### Checklist and Review Events

- checklist created
- checklist updated
- checklist response submitted
- evidence uploaded
- comparison generated

### Repository Events

- repository entry created
- repository entry updated
- repository status changed
- repository document uploaded
- repository timeline update added

### Cleanup Events

- cleanup job started
- cleanup item archived
- cleanup completed
- cleanup failed

## Audit Detail Expectations

- role changes should capture old and new role
- assignment changes should capture old and new assignee where possible
- status changes should capture old and new status
- archive actions should identify what category of data was archived
- blocked access attempts should preserve enough context for security review

## What Audit Logs Are Not

- not a replacement for full business data
- not a place to store entire file contents
- not a social message history viewer

## Retention Rule for Audit Logs

Audit logs are permanent records for MVP purposes and must not be removed by the 14-day cleanup process.

## Phase 1 Note

Login and logout audit logging is intentionally deferred to the audit implementation phase. Phase 1 only verifies Laravel Breeze authentication and protected dashboard access.

Do not implement audit logging yet.

Phase 2 note: Role assignment and permission changes must be audit-logged in the future audit implementation phase. Phase 2 only establishes role and permission enforcement.

Phase 3 note: Core workflow tables are designed with created_by, assigned_by, uploaded_by, timestamps, archived_at, and status fields to support future audit logging. Full activity log implementation remains deferred to the audit phase.

Phase 4 note: Dashboard access control is enforced through authentication and role/permission middleware. Detailed dashboard access audit logging remains deferred to the audit implementation phase.

Phase 5 note: Repository entry creation, updates, status changes, and timeline updates must be audit-logged in the future audit implementation phase. Phase 5 preserves repository timeline history through repository_updates but does not implement the full activity log yet.

Phase 6 note: Project creation, project updates, Coordinator assignment, and Coordinator reassignment must be audit-logged in the future audit implementation phase. Phase 6 preserves assignment history through project_assignments but does not implement the full activity log yet.

Phase 7 note: Task creation, subtask creation, Subordinate assignment, Subordinate revocation, and Subordinate progress updates must be audit-logged in the future audit implementation phase. Phase 7 preserves Subordinate assignment history through subtask_assignments but does not implement the full activity log yet.

## Review Use Cases

The audit trail should help answer:

- who changed a role
- who assigned a Coordinator
- who uploaded a file
- who approved a task
- when a project became completed
- whether cleanup ran correctly
- whether someone tried to access restricted content

## Phase 0 Lock

Audit logging is mandatory for all critical workflow and security-sensitive events listed here.


