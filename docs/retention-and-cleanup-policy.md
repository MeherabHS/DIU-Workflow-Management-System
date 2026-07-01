# DIUS Management Portal Retention and Cleanup Policy

## File: `docs/retention-and-cleanup-policy.md`

## Purpose

This document defines what data stays active, what can be archived after project completion, and what must never be permanently removed in the MVP.

## Policy Goal

Keep the system operationally clean without sacrificing accountability, institutional memory, or proof of work.

## Core Rule

After a project is marked completed, temporary operational chats, follow-up documents, chat attachments, and draft working files may be archived after 14 days.

This rule exists to reduce active clutter, not to erase evidence.

## Permanent Preservation Rule

The following data must never be permanently removed by automated cleanup:

- project metadata
- repository records
- final reports
- assignment history
- approval and rejection history
- checklist comparison results
- activity logs
- audit trail
- repository timeline

## Retention Categories

| Data Type | During Active Project | After Completion + 14 Days |
|---|---|---|
| Project metadata | Active | Preserve |
| Repository records | Active | Preserve |
| Repository timeline | Active | Preserve |
| Final reports | Active | Preserve |
| Assignment history | Active | Preserve |
| Approval and rejection history | Active | Preserve |
| Checklist comparison results | Active | Preserve |
| Activity logs | Active | Preserve |
| Audit trail | Active | Preserve |
| Temporary chat messages | Active | Archive |
| Chat attachments | Active | Archive |
| Follow-up documents | Active | Archive |
| Draft working files | Active | Archive or remove by policy |
| Reminder notifications | Active | Archive or prune later if safe |

## Archive Principles

- archive before delete where practical
- cleanup must be scheduled, not manual by default
- archived data should remain traceable by metadata
- archive actions must be logged
- active and permanent views must still function after cleanup

## Cleanup Trigger

- cleanup begins only for projects formally marked completed
- cleanup must wait at least 14 full days from completion timestamp
- active projects must never be touched by cleanup logic

## Cleanup Scope

The scheduler job in later phases should:

1. find projects completed more than 14 days ago
2. identify temporary operational content linked to those projects
3. archive approved content categories
4. preserve protected categories
5. record cleanup logs
6. record failures if any archive action does not complete safely

## Protected Data Rules

Cleanup must never:

- delete project identity
- delete repository entry
- delete final report
- delete checklist result
- delete approval or rejection evidence
- delete assignment history
- delete audit logs

## Draft File Rule

Draft working files may be archived or removed only if:

- they are not marked as final
- they are not required by approval history
- they are not the only evidence for a checklist or submission outcome

If there is any doubt, archive instead of delete.

## Message Retention Rule

- message metadata should remain traceable
- message body may be archived later if business policy permits
- sender, receiver, project context, timestamp, and archive event must remain known

## Failure Handling

If cleanup fails:

- cleanup must stop safely
- the failure must be logged
- preserved records must remain untouched
- partial cleanup must be traceable

## Manual Override

Any future manual archive or restore action should be limited to Admin control and must be logged.

## Laravel Implementation Direction

Later implementation should use:

- Laravel Scheduler for cleanup execution
- private storage for archived files
- database records to mark archive state
- audit logging for start, item-level events where needed, completion, and failure

## Phase 0 Lock

The cleanup policy is locked as archive-first, evidence-preserving, and safe for institutional accountability.
