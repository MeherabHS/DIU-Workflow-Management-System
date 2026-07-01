# Role Permission Matrix — DIUS Management Portal

## Roles

| Role | Description |
|------|-------------|
| **Admin** | Full system access, user management, audit trail |
| **PM/Manager** | Project management, repository, reports |
| **Coordinator** | Assigned project management, task/work item creation, subordinate assignment |
| **Subordinate** | Assigned work item progress updates only |

## Permission Matrix

| Permission | Admin | PM/Manager | Coordinator | Subordinate |
|------------|:-----:|:----------:|:-----------:|:-----------:|
| `access admin dashboard` | ✅ | | | |
| `access pm dashboard` | ✅ | ✅ | | |
| `access coordinator dashboard` | ✅ | ✅ | ✅ | |
| `access subordinate dashboard` | ✅ | ✅ | ✅ | ✅ |
| `manage users` | ✅ | | | |
| `assign roles` | ✅ | | | |
| `view audit trail` | ✅ | | | |
| `view reports` | ✅ | ✅ | | |
| `export reports` | ✅ | ✅ | | |
| `view own profile` | ✅ | ✅ | ✅ | ✅ |
| `update own profile` | ✅ | ✅ | ✅ | ✅ |
| `view repository` | ✅ | ✅ | ✅ | |
| `create repository entry` | ✅ | ✅ | | |
| `update repository entry` | ✅ | ✅ | | |
| `add repository update` | ✅ | ✅ | | |
| `view projects` | ✅ | ✅ | ✅ | |
| `create project` | ✅ | ✅ | | |
| `update project` | ✅ | ✅ | | |
| `assign coordinator` | ✅ | ✅ | | |
| `view assigned projects` | ✅ | ✅ | ✅ | |
| `view project tasks` | ✅ | ✅ | ✅ | |
| `create project task` | ✅ | ✅ | ✅ | |
| `update project task` | ✅ | ✅ | ✅ | |
| `create project subtask` | ✅ | ✅ | ✅ | |
| `update project subtask` | ✅ | ✅ | ✅ | |
| `assign subordinate` | ✅ | ✅ | ✅ | |
| `revoke subordinate assignment` | ✅ | ✅ | ✅ | |
| `view assigned subtasks` | ✅ | ✅ | ✅ | ✅ |
| `update assigned subtask progress` | ✅ | ✅ | ✅ | ✅ |
| `view workflow messages` | ✅ | ✅ | ✅ | ✅ |
| `create workflow message` | ✅ | ✅ | ✅ | ✅ |
| `delete workflow message` | ✅ | ✅ | ✅ | ✅ |
| `view workflow files` | ✅ | ✅ | ✅ | ✅ |
| `upload workflow file` | ✅ | ✅ | ✅ | ✅ |
| `download workflow file` | ✅ | ✅ | ✅ | ✅ |
| `delete workflow file` | ✅ | ✅ | ✅ | ✅ |

## Dashboard Access

| Dashboard | Admin | PM/Manager | Coordinator | Subordinate |
|-----------|:-----:|:----------:|:-----------:|:-----------:|
| Admin Dashboard | ✅ | | | |
| PM Dashboard | ✅ | ✅ | | |
| Coordinator Dashboard | ✅ | ✅ | ✅ | |
| Subordinate Dashboard | ✅ | ✅ | ✅ | ✅ |

## Navigation Links

| Link | Admin | PM/Manager | Coordinator | Subordinate |
|------|:-----:|:----------:|:-----------:|:-----------:|
| Dashboard | ✅ | ✅ | ✅ | ✅ |
| Admin Dashboard | ✅ | | | |
| PM Dashboard | ✅ | ✅ | | |
| Coordinator Dashboard | ✅ | ✅ | ✅ | |
| Subordinate Dashboard | ✅ | ✅ | ✅ | ✅ |
| Users | ✅ | | | |
| Audit Trail | ✅ | | | |
| Reports | ✅ | ✅ | | |
| Projects | ✅ | ✅ | ✅ | |
| My Assigned Projects | | | ✅ | |
| Repository Tracker | ✅ | ✅ | ✅ | |
| My Work Items | | | | ✅ |

## Data Scoping

| Data Type | Admin | PM/Manager | Coordinator | Subordinate |
|-----------|-------|------------|-------------|-------------|
| All projects | ✅ | ✅ (managed) | ✅ (assigned) | ❌ |
| All tasks | ✅ | ✅ | ✅ (in assigned projects) |  |
| All work items | ✅ | ✅ | ✅ (in assigned projects) | ✅ (assigned only) |
| All messages | ✅ | ✅ | ✅ (in scope) | ✅ (own work items) |
| All files | ✅ | ✅ | ✅ (in scope) | ✅ (own work items) |
| All notifications | ✅ (own) | ✅ (own) | ✅ (own) | ✅ (own) |
| All audit logs | ✅ | ❌ | ❌ | ❌ |
| All reports | ✅ | ✅ (scoped) | ❌ | ❌ |
