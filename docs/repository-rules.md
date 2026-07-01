# DIUS Management Portal Repository Rules

## File: `docs/repository-rules.md`

## Purpose

This document defines the repository module rules for the MVP. The repository acts as a simple tender or project tracker that preserves institutional memory for active and completed work.

## Repository Goal

The repository should provide a structured view of project records so authorized users can track:

- what project exists
- what type of project it is
- which department or client it belongs to
- who is responsible
- what documents exist
- what the current status is
- what timeline updates occurred

## Repository Scope

The repository is not just a file list. It is a controlled record of project identity and progress.

The repository must support:

- project entry creation
- project entry update
- status tracking
- file attachment
- timeline updates
- search
- filter
- archived visibility for preserved records

## Minimum Repository Fields

| Field | Description |
|---|---|
| Project title | Main project name |
| Project type | Internal, Tender, Admin, Academic, or similar controlled type |
| Department or client | Responsible office or stakeholder |
| Deadline | Target completion date |
| Status | Planned, Ongoing, Submitted, Completed, Archived |
| Responsible person | PM or Coordinator with responsibility |
| Budget or value | Optional numeric or descriptive field |
| Summary | Short description |
| Documents | Linked repository documents |
| Timeline updates | Chronological record of changes |

## Repository Status Rules

| Status | Meaning |
|---|---|
| Planned | Recorded but not active yet |
| Ongoing | Work is active |
| Submitted | Major output submitted for review |
| Completed | Work accepted and finished |
| Archived | Operational work archived, permanent record retained |

## Repository Document Categories

- ToR or instruction document
- proposal or planning document
- approval document
- budget document
- progress evidence
- final report
- closing document

## Repository Ownership Rules

- PM normally creates or owns the project repository record
- Coordinator may update status or add documents if permitted
- Admin may view and govern repository behavior
- Subordinate should not directly manage repository records in MVP

## Timeline Rules

Each important repository change should produce a timeline update, such as:

- repository entry created
- deadline changed
- status changed
- document uploaded
- responsible person changed
- project marked completed
- archive status applied

## Relationship to Project Workflow

- every major project should have one corresponding repository identity
- repository record should outlive temporary task chatter
- cleanup should not remove repository summary, timeline, or preserved documents
- repository should reflect project reality, not replace project task details

## Search and Filter Requirements

The repository must support search or filter by:

- title
- type
- department or client
- status
- responsible person
- deadline

## MVP Repository Rules

- keep status options controlled and limited
- do not add advanced analytics yet
- do not add public external access
- do not allow random document dumping without repository context
- do not make the repository depend on chat data for meaning

## Audit Requirements

These repository actions must be logged:

- entry created
- entry updated
- status changed
- document uploaded
- document archived
- timeline update added
- unauthorized access attempt

## Preservation Rules

The following repository elements are permanent and must not be permanently removed by cleanup:

- repository entry metadata
- status history
- responsible person history where stored
- final documents
- timeline updates
- links to final project completion state

## Phase 0 Lock

The repository is locked as a controlled internal tracker, not a generic file cabinet and not a public-facing tender portal.
