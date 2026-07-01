# DIUS Management Portal Testing Strategy

## File: `docs/testing-strategy.md`

## Purpose

This document defines how the MVP should be tested phase by phase so the system becomes trustworthy, not just functional.

## Testing Goal

The testing strategy must protect the highest-risk behaviors:

- role boundaries
- assignment visibility
- file security
- workflow correctness
- repository integrity
- audit coverage
- cleanup safety

## Required Test Types for Future Phases

Each future phase must include the following where applicable:

- feature tests
- permission tests
- workflow tests
- audit log tests
- file security tests

## Testing Principles

- test business-critical paths before low-risk convenience features
- test both allowed and blocked behavior
- verify audit log creation for critical actions
- verify cleanup preserves permanent records
- keep tests readable and closely tied to documented workflows

## Core Test Categories

## Authentication Tests

- user can register if registration is enabled for the phase
- user can log in
- wrong password is blocked
- guest is redirected from protected pages

## Role and Permission Tests

- Admin can access admin-only areas
- PM cannot access admin-only pages
- Coordinator cannot create unauthorized top-level project
- Subordinate can only access assigned work
- unauthorized thread and file access are blocked

## Project Workflow Tests

- PM creates project
- PM assigns Coordinator
- assigned Coordinator can see project
- unrelated Coordinator cannot see project
- project completion requires valid workflow state

## Task and Subtask Workflow Tests

- Coordinator creates subtask under assigned project
- Coordinator assigns one or more Subordinates
- Subordinate updates progress
- reviewer requests revision
- revised work can be resubmitted

## Communication Tests

- PM can message assigned Coordinator in context
- Coordinator can reply to PM in context
- Coordinator can message assigned Subordinate
- Subordinate can reply to assigned Coordinator
- unassigned user cannot access thread

## File Security Tests

- allowed file type uploads succeed
- blocked file type uploads fail
- unauthorized download fails
- authorized download succeeds
- archive action does not break final file access

## Notification Tests

- assignment notification reaches correct user
- message notification reaches correct user
- deadline reminder is created for correct assignee
- overdue alert reaches assignee and supervisor where required

## Checklist Tests

- PM creates checklist
- user submits checklist response
- missing item appears as not done
- evidence file links correctly
- comparison result is preserved

## Approval Workflow Tests

- work submission changes status correctly
- approval changes status correctly
- rejection or revision changes status correctly
- project completion preserves required records

## Repository Tests

- repository entry created
- status updated if permitted
- search returns matching entries
- filter returns correct entries
- repository remains visible after cleanup

## Cleanup and Archive Tests

- only completed projects older than 14 days are selected
- temporary chat content is archived
- final reports remain preserved
- repository records remain preserved
- active projects are untouched
- cleanup failure is logged

## Audit Log Tests

- project created log exists
- assignment log exists
- file upload log exists
- message sent log exists
- approval log exists
- cleanup log exists
- unauthorized access log exists

## Test Data Guidance

- use clearly separated users for Admin, PM, Coordinator, and Subordinate
- create assigned and unassigned scenarios
- create both temporary and permanent file categories
- create completed and active projects for cleanup testing

## Phase-by-Phase Expectation

- every phase should add tests for newly introduced behavior
- no major workflow should be considered complete without its blocked-case tests
- no critical audit event should be introduced without a log assertion

## MVP Testing Exit Standard

Before Phase 15 is considered complete, the test suite should demonstrate:

- core role boundaries hold
- core workflow paths succeed
- blocked paths are rejected
- critical logs are generated
- cleanup is safe

## Phase 0 Lock

Testing is a first-class requirement of the MVP, not a final polish step.
