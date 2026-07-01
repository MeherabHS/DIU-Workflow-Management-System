# DIUS Management Portal Roles and Permissions

## File: `docs/roles-and-permissions.md`

## Purpose

This document defines the four core roles for the MVP and the permission boundaries that future development must follow.

## Core Roles

- Admin
- PM/Manager
- Coordinator
- Subordinate

## Permission Model Principles

- users must only access what their role allows
- access should be restricted further by assignment context where applicable
- project-level visibility does not automatically grant system-wide access
- upload permission and download permission must be checked separately
- permission failures must be blocked and logged

## Admin

### Main Responsibility

System governance and monitoring.

### Allowed Actions

- approve or block user accounts
- assign or change roles
- manage departments
- manage cleanup settings later
- view audit logs and system monitoring views
- restore archived records if the future design allows it
- view repository records

### Restricted Actions

- should not act as the PM by default in normal workflow
- should not bypass audit logging

## PM/Manager

### Main Responsibility

Own project creation, coordinator assignment, project review, and final approval decisions.

### Allowed Actions

- create and update projects
- assign project to one or more Coordinators where business rules allow
- upload project instructions and official files
- create project-level tasks if needed
- communicate with assigned Coordinators
- create checklist requirements
- review submissions
- approve, reject, or request revision
- mark project completed
- create and update repository entries within permitted scope
- view activity and status relevant to owned projects

### Restricted Actions

- cannot manage system roles unless granted Admin access
- cannot access unrelated private project threads without assignment or ownership
- cannot manage cleanup settings in MVP

## Coordinator

### Main Responsibility

Execute assigned projects, coordinate subtasks, supervise Subordinates, and report progress upward.

### Allowed Actions

- view assigned projects
- create tasks or subtasks under assigned projects
- assign one or more Subordinates to subtasks
- communicate with assigned PMs and assigned Subordinates
- upload progress files and operational files
- submit updates and final work for review
- update repository status where allowed
- respond to revision requests
- view assigned notifications and follow-ups

### Restricted Actions

- cannot create top-level projects unless future business rules allow it
- cannot access unrelated projects
- cannot approve project-level completion unless explicitly allowed by workflow
- cannot change system roles

## Subordinate

### Main Responsibility

Perform assigned subtasks and submit evidence of work.

### Allowed Actions

- view assigned subtasks
- communicate with the responsible Coordinator in assignment context
- upload work files and supporting evidence
- update progress
- submit work
- respond to revision requests
- view notifications relevant to assigned work

### Restricted Actions

- cannot create projects
- cannot assign other users
- cannot access unrelated tasks or project threads
- cannot approve final work
- cannot access admin controls

## Permission Matrix

| Capability | Admin | PM/Manager | Coordinator | Subordinate |
|---|---|---|---|---|
| Login and use dashboard | Yes | Yes | Yes | Yes |
| Approve user account | Yes | No | No | No |
| Change user role | Yes | No | No | No |
| Manage departments | Yes | No | No | No |
| Create project | Optional by policy | Yes | No | No |
| Assign Coordinator | Optional by policy | Yes | No | No |
| View owned or assigned projects | Yes | Yes | Yes | Limited |
| Create task | Optional | Yes | Yes in assigned project | No |
| Create subtask | Optional | Optional | Yes | No |
| Assign Subordinate | No by default | Optional | Yes | No |
| Message PM in context | Optional | Yes | Yes | No |
| Message Coordinator in context | Optional | Yes | Yes | Yes |
| Upload file in permitted context | Yes | Yes | Yes | Yes |
| Download file in permitted context | Yes | Yes | Yes | Yes |
| Create checklist | Optional | Yes | Optional later | No |
| Submit checklist response | No | Optional | Yes | Yes |
| Approve submission | Optional | Yes | Yes for subordinate work | No |
| Mark project completed | Optional | Yes | No |
| View audit panel | Yes | Limited by policy | No | No |
| Trigger cleanup manually later | Yes | No | No | No |

## Assignment-Based Visibility Rules

- PM can see projects they created or are responsible for
- Coordinator can see projects assigned to them
- Subordinate can see only subtasks assigned to them and limited parent context needed to complete the work
- users cannot browse unrelated communications or files even if they share the same department

## Communication Permission Rules

- PM can communicate with assigned Coordinators only within project or task context
- Coordinator can communicate with assigned PMs and assigned Subordinates within project, task, or subtask context
- Subordinate can communicate with assigned Coordinator within subtask or project context where allowed
- general open chat across all users is out of scope for MVP

## File Permission Rules

- files must belong to a project, task, subtask, message, checklist item, or repository entry
- uploaders do not gain unrestricted visibility to unrelated files
- download attempts must be checked against role and assignment
- unauthorized file access attempts must be logged

## Audit Expectations

These permission-sensitive actions must always be logged:

- account approval or blocking
- role change
- assignment change
- unauthorized access attempt
- sensitive file access
- approval or rejection action

## Phase 0 Lock

These four roles and their boundaries are locked for MVP development unless the scope is explicitly reopened later.
