# DIUS Management Portal Phase Roadmap

## File: `docs/phase-roadmap.md`

## Purpose

This roadmap defines the approved build order for the MVP and keeps future development aligned with the locked scope.

## Approved Phase Order

| Phase | Name | Main Goal |
|---|---|---|
| 0 | Documentation and scope | Lock requirements and rules before coding |
| 1 | Laravel setup and auth | Create project foundation and login flow |
| 2 | Roles and permissions | Enforce access boundaries |
| 3 | Database migrations | Build core data structure |
| 4 | Role dashboards | Show relevant work by role |
| 5 | Repository module | Add tender-style project tracker |
| 6 | PM to Coordinator assignment | Create top-level project assignment workflow |
| 7 | Coordinator to Subordinate task system | Add task delegation flow |
| 8 | Internal communication system | Add project-linked messaging |
| 9 | File upload and download | Add secure file handling |
| 10 | Notifications and follow-up notices | Add alerts and reminders |
| 11 | Checklist report comparison | Compare required and submitted work |
| 12 | Approval, rejection, and completion | Formalize review lifecycle |
| 13 | Cleanup and archive system | Archive temporary data after 14 days |
| 14 | Audit trail and admin monitoring | Make accountability reviewable |
| 15 | Final testing and hardening | Stabilize MVP |

## Phase 0 Output

Phase 0 must produce:

- README
- vision and scope
- roles and permissions
- workflow lifecycle
- repository rules
- retention and cleanup policy
- audit log rules
- testing strategy
- phase roadmap

## Delivery Rule

Each later phase should only begin after:

- the previous phase outputs exist
- the previous phase tests are passing at its expected level
- the new phase does not break Phase 0 scope lock

## Phase Priorities

### Foundation Phases

- Phase 1
- Phase 2
- Phase 3

These create the technical base needed for all later workflow logic.

### Operational Workflow Phases

- Phase 4
- Phase 5
- Phase 6
- Phase 7
- Phase 8
- Phase 9
- Phase 10
- Phase 11
- Phase 12

These deliver the visible business workflow of the portal.

### Control and Safety Phases

- Phase 13
- Phase 14
- Phase 15

These phases make the MVP safe, reviewable, and deployment-ready.

## Non-Negotiable Constraints Across All Phases

- use Laravel + MySQL
- keep files in private storage
- use database notifications later
- use Laravel Scheduler later
- no AI feature for MVP
- no mobile app for MVP
- no WebSocket real-time chat for MVP
- no general random chat for MVP

## Quality Gates by Roadmap

| Area | Quality Gate |
|---|---|
| Scope | No unapproved feature drift |
| Permissions | Block unauthorized access |
| Workflow | Status transitions remain valid |
| Files | Unsafe file types are rejected |
| Repository | Final records remain preserved |
| Cleanup | Temporary data archived safely |
| Audit | Critical actions remain traceable |
| Tests | New behavior adds matching tests |

## Completion Meaning

The roadmap is complete only when the MVP can demonstrate:

- correct role-based operation
- project to task to subtask delegation
- linked communication and files
- preserved repository history
- safe cleanup behavior
- useful audit visibility
- stable automated tests

## Phase 0 Lock

This roadmap is the approved implementation sequence for the MVP unless you intentionally change the scope later.
