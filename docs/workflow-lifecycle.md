# DIUS Management Portal Workflow Lifecycle

## File: `docs/workflow-lifecycle.md`

## Purpose

This document defines the end-to-end operational lifecycle of a project from creation to archive, including assignment, communication, submission, approval, and cleanup.

## Lifecycle Overview

The MVP lifecycle has four connected layers:

1. project setup
2. task delegation
3. execution and review
4. completion and archive

## Project Lifecycle

| Stage | Description | Main Actor |
|---|---|---|
| Draft | Project record is being created | PM/Manager |
| Active | Project is live and assigned | PM/Manager |
| In Progress | Coordinator and Subordinates are working | Coordinator |
| Submitted | Required work has been submitted for final review | Coordinator or PM |
| Completed | PM has accepted final completion | PM/Manager |
| Archive Pending | Completed project is waiting for retention window | System |
| Archived | Temporary operational content archived, permanent records retained | System |

## Project Workflow

1. PM creates project.
2. PM adds description, deadline, files, and repository-linked details.
3. PM assigns one or more Coordinators if the final business rule permits it.
4. Coordinator receives notification.
5. Project becomes active.
6. Coordinator creates tasks or subtasks under the project.
7. Coordinator assigns Subordinates where needed.
8. Users communicate inside project-linked threads.
9. Users upload files and progress updates.
10. Work is submitted for review.
11. Reviewer approves, rejects, or requests revision.
12. PM marks project completed after all required work is accepted.
13. System waits 14 days.
14. Cleanup process archives temporary operational data.
15. Permanent records remain visible in repository and audit history.

## Task and Subtask Lifecycle

| Stage | Description |
|---|---|
| Pending | Task exists but has not yet been accepted |
| Accepted | Assignee acknowledged the work |
| In Progress | Work has started |
| Submitted | Assignee submitted work for review |
| Revision Required | Reviewer sent it back for correction |
| Resubmitted | Revised work sent again |
| Approved | Reviewer accepted the work |
| Rejected | Work is closed as unacceptable if workflow needs this state |

## Task Delegation Rules

- PM can create project-level tasks when needed
- Coordinator can create subtasks under assigned projects
- Coordinator remains accountable to the PM even when work is delegated
- Subordinate completes assigned work but does not own project-level approval
- forwarding must be logged and should not erase original accountability

## Communication Lifecycle

Communication is mandatory but must stay structured.

### Required Communication Paths

- PM to Coordinator
- Coordinator to PM
- Coordinator to Subordinate
- Subordinate to Coordinator

### Communication Rules

- every message must be linked to project, task, or subtask context
- general random chat is out of scope
- messages may include attachments
- metadata must remain traceable even if content is archived later

## File Lifecycle

| File Type | Active Phase | After Completion |
|---|---|---|
| Project instruction files | Available to permitted users | Preserve as needed |
| Task working files | Available to permitted users | Archive if temporary |
| Chat attachments | Available in thread | Archive after retention window |
| Draft files | Available during execution | Archive or remove if policy allows |
| Final reports | Available | Preserve permanently |
| Repository documents | Available | Preserve permanently |

## Checklist and Review Lifecycle

1. PM creates checklist for required deliverables.
2. Coordinator or Subordinate submits responses.
3. Evidence files may be attached to checklist items.
4. System compares required items against submitted items.
5. Reviewer checks result.
6. Reviewer approves or requests revision.

## Approval Workflow

| Action | Result |
|---|---|
| Submit work | Status becomes submitted |
| Approve work | Status becomes approved |
| Reject work | Status becomes rejected or closed |
| Request revision | Status becomes revision required |
| Resubmit work | Status becomes resubmitted |
| Mark project completed | Project enters completed state |

## Completion and Archive Logic

- project completion is a formal PM decision
- completion does not delete records
- after 14 days, temporary operational materials may be archived
- archived operational content should not break the repository, project summary, or audit trail

## Workflow Failure Conditions to Prevent

- user sees project not assigned to them
- subordinate bypasses Coordinator approval
- project is marked completed without traceable approval path
- cleanup removes final report or repository history
- files remain publicly exposed

## Phase 0 Lock

This lifecycle is the base operational model for all later Laravel implementation phases.
