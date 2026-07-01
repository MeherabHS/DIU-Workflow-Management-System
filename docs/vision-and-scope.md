# DIUS Management Portal Vision and Scope

## File: `docs/vision-and-scope.md`

## Vision

The DIUS Project, Communication & Repository Management Portal will be a Laravel + MySQL web application for managing internal projects with strong accountability, clear delegation, and traceable operational history.

The portal exists to solve a practical internal coordination problem:

- PMs or Managers need to assign work and monitor outcomes
- Coordinators need to break work into manageable subtasks
- Subordinates need a controlled space to receive, update, and submit assigned work
- the organization needs a repository-style record of project history, documents, and timeline updates
- the system must preserve accountability even when temporary operational data is archived later

## Primary Goal

Create a web portal where project assignment, communication, files, follow-ups, approvals, repository records, and audit evidence are linked together and remain traceable throughout the project lifecycle.

## Problem Statement

Without a structured system, internal project work becomes fragmented across messages, files, verbal instructions, and incomplete records. That makes it difficult to answer important questions such as:

- who assigned the work
- who received the work
- what files were shared
- what follow-up happened
- what was approved or rejected
- what final outputs were preserved
- when the project was completed

This portal will centralize those actions in a controlled application workflow.

## MVP Scope

The MVP includes:

- user authentication
- role-based access
- role-based dashboards
- project repository management
- project creation and coordinator assignment
- task and subtask assignment
- project-linked communication
- secure file handling
- notifications and follow-up notices
- checklist-based comparison reporting
- approval and rejection flow
- project completion flow
- 14-day archive and cleanup logic
- audit trail visibility
- test coverage for critical workflows

## Mandatory Business Rules

- PM to Coordinator communication must exist
- Coordinator to Subordinate communication must exist
- communication must belong to a project, task, or subtask context
- general random chat is not required for MVP
- project completion must not remove permanent records
- temporary chat and working files may be archived after 14 days
- repository records must remain available after cleanup
- critical actions must produce audit evidence

## In Scope

- Laravel web application
- MySQL relational database
- private file storage
- database-backed notifications
- scheduled cleanup and reminders
- repository timeline tracking
- workflow status tracking
- assignment and approval history

## Out of Scope

- AI-generated report comparison
- mobile app
- real-time WebSocket messaging
- organization-wide social chat
- advanced analytics beyond basic dashboard summaries
- external client portal

## Intended Users

- Admins managing system-level control
- PMs or Managers running projects
- Coordinators executing middle-layer operational control
- Subordinates completing assigned work

## Quality Priorities

The MVP should prioritize:

1. correctness of role boundaries
2. clarity of workflow
3. security of files
4. integrity of repository records
5. preservation of audit evidence
6. safe cleanup behavior

## Success Conditions

The system is successful when it can reliably answer:

- what project exists
- who is responsible
- what work is pending
- what communication took place in context
- what files were exchanged
- what was submitted
- what was approved or rejected
- what final records remain after cleanup

## Scope Lock Decision

Phase 0 locks the MVP as an accountability-first internal web portal. Future phases should not add AI, mobile, or real-time infrastructure unless the scope is intentionally reopened later.
