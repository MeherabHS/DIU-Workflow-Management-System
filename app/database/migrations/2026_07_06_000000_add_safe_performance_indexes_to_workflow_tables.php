<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a guarded index when the table and columns exist and no equivalent
     * index is already present.
     *
     * @param  array<int, string>  $columns
     */
    private function addIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->columnsExist($table, $columns)) {
            return;
        }

        if ($this->indexExists($table, $indexName, $columns)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName): void {
            $table->index($columns, $indexName);
        });
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function columnsExist(string $table, array $columns): bool
    {
        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check by explicit index name and by exact column sequence where the
     * framework exposes portable index metadata.
     *
     * @param  array<int, string>|null  $columns
     */
    private function indexExists(string $table, string $indexName, ?array $columns = null): bool
    {
        try {
            foreach (Schema::getIndexes($table) as $index) {
                $name = $index['name'] ?? $index['index'] ?? null;
                $indexColumns = $index['columns'] ?? [];

                if ($name === $indexName) {
                    return true;
                }

                if ($columns !== null && $indexColumns === $columns) {
                    return true;
                }
            }
        } catch (Throwable) {
            // Fall back to name-based checks below for older drivers.
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                'SELECT to_regclass(?) AS regclass',
                ["public.{$indexName}"]
            );

            return $result && $result->regclass !== null;
        }

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    public function up(): void
    {
        $this->addIndexIfMissing('projects', ['created_by', 'id'], 'idx_projects_created_by_id');
        $this->addIndexIfMissing('projects', ['updated_at'], 'idx_projects_updated_at');
        $this->addIndexIfMissing('projects', ['status', 'deadline'], 'idx_projects_status_deadline');
        $this->addIndexIfMissing('projects', ['priority', 'deadline', 'created_at'], 'idx_projects_priority_deadline_created');

        $this->addIndexIfMissing('project_assignments', ['project_id', 'assignment_role', 'revoked_at', 'assigned_at'], 'idx_pa_project_role_revoked_assigned');
        $this->addIndexIfMissing('project_assignments', ['coordinator_id', 'project_id'], 'idx_pa_coordinator_project');

        $this->addIndexIfMissing('tasks', ['project_id', 'updated_at'], 'idx_tasks_project_updated');
        $this->addIndexIfMissing('tasks', ['deadline', 'status'], 'idx_tasks_deadline_status');
        $this->addIndexIfMissing('tasks', ['project_id', 'status'], 'idx_tasks_project_status_safe');

        $this->addIndexIfMissing('subtasks', ['task_id', 'updated_at'], 'idx_subtasks_task_updated');
        $this->addIndexIfMissing('subtasks', ['deadline', 'status'], 'idx_subtasks_deadline_status');
        $this->addIndexIfMissing('subtasks', ['status', 'updated_at'], 'idx_subtasks_status_updated');

        $this->addIndexIfMissing('subtask_assignments', ['subtask_id', 'revoked_at', 'assigned_at'], 'idx_sa_subtask_revoked_assigned');
        $this->addIndexIfMissing('subtask_assignments', ['subordinate_id', 'revoked_at'], 'idx_sa_subordinate_revoked');

        $this->addIndexIfMissing('repository_entries', ['project_id', 'finalized_at'], 'idx_repo_entries_project_finalized');
        $this->addIndexIfMissing('repository_entries', ['status', 'updated_at'], 'idx_repo_entries_status_updated');
        $this->addIndexIfMissing('repository_entries', ['department_id', 'updated_at'], 'idx_repo_entries_department_updated');
        $this->addIndexIfMissing('repository_entries', ['type', 'updated_at'], 'idx_repo_entries_type_updated');
        $this->addIndexIfMissing('repository_entries', ['deadline', 'updated_at'], 'idx_repo_entries_deadline_updated');

        $this->addIndexIfMissing('repository_updates', ['repository_entry_id', 'created_at'], 'idx_repo_updates_entry_created');

        $this->addIndexIfMissing('workflow_messages', ['project_id', 'task_id', 'subtask_id', 'created_at'], 'idx_wf_messages_context_created');

        $this->addIndexIfMissing('workflow_notifications', ['user_id', 'created_at'], 'idx_wf_notifications_user_created');
        $this->addIndexIfMissing('workflow_notifications', ['user_id', 'type', 'created_at'], 'idx_wf_notifications_user_type_created');

        $this->addIndexIfMissing('workflow_audit_logs', ['created_at'], 'idx_wf_audit_logs_created');
        $this->addIndexIfMissing('workflow_audit_logs', ['project_id', 'created_at'], 'idx_wf_audit_logs_project_created');
        $this->addIndexIfMissing('workflow_audit_logs', ['actor_id', 'created_at'], 'idx_wf_audit_logs_actor_created');

        $this->addIndexIfMissing('users', ['updated_at'], 'idx_users_updated_at');
    }

    public function down(): void
    {
        $this->addIndexIfMissing('projects', ['created_by'], 'idx_projects_created_by_fk');
        $this->addIndexIfMissing('project_assignments', ['project_id'], 'idx_pa_project_fk');
        $this->addIndexIfMissing('project_assignments', ['coordinator_id'], 'idx_pa_coordinator_fk');
        $this->addIndexIfMissing('tasks', ['project_id'], 'idx_tasks_project_fk');
        $this->addIndexIfMissing('subtasks', ['task_id'], 'idx_subtasks_task_fk');
        $this->addIndexIfMissing('subtask_assignments', ['subtask_id'], 'idx_sa_subtask_fk');
        $this->addIndexIfMissing('subtask_assignments', ['subordinate_id'], 'idx_sa_subordinate_fk');
        $this->addIndexIfMissing('repository_entries', ['project_id'], 'idx_repo_entries_project_fk');
        $this->addIndexIfMissing('repository_entries', ['department_id'], 'idx_repo_entries_department_fk');
        $this->addIndexIfMissing('repository_updates', ['repository_entry_id'], 'idx_repo_updates_entry_fk');
        $this->addIndexIfMissing('workflow_messages', ['project_id'], 'idx_wf_messages_project_fk');
        $this->addIndexIfMissing('workflow_notifications', ['user_id'], 'idx_wf_notifications_user_fk');
        $this->addIndexIfMissing('workflow_audit_logs', ['project_id'], 'idx_wf_audit_logs_project_fk');
        $this->addIndexIfMissing('workflow_audit_logs', ['actor_id'], 'idx_wf_audit_logs_actor_fk');

        $this->dropIndexIfExists('users', 'idx_users_updated_at');

        $this->dropIndexIfExists('workflow_audit_logs', 'idx_wf_audit_logs_actor_created');
        $this->dropIndexIfExists('workflow_audit_logs', 'idx_wf_audit_logs_project_created');
        $this->dropIndexIfExists('workflow_audit_logs', 'idx_wf_audit_logs_created');

        $this->dropIndexIfExists('workflow_notifications', 'idx_wf_notifications_user_type_created');
        $this->dropIndexIfExists('workflow_notifications', 'idx_wf_notifications_user_created');

        $this->dropIndexIfExists('workflow_messages', 'idx_wf_messages_context_created');

        $this->dropIndexIfExists('repository_updates', 'idx_repo_updates_entry_created');

        $this->dropIndexIfExists('repository_entries', 'idx_repo_entries_deadline_updated');
        $this->dropIndexIfExists('repository_entries', 'idx_repo_entries_type_updated');
        $this->dropIndexIfExists('repository_entries', 'idx_repo_entries_department_updated');
        $this->dropIndexIfExists('repository_entries', 'idx_repo_entries_status_updated');
        $this->dropIndexIfExists('repository_entries', 'idx_repo_entries_project_finalized');

        $this->dropIndexIfExists('subtask_assignments', 'idx_sa_subordinate_revoked');
        $this->dropIndexIfExists('subtask_assignments', 'idx_sa_subtask_revoked_assigned');

        $this->dropIndexIfExists('subtasks', 'idx_subtasks_status_updated');
        $this->dropIndexIfExists('subtasks', 'idx_subtasks_deadline_status');
        $this->dropIndexIfExists('subtasks', 'idx_subtasks_task_updated');

        $this->dropIndexIfExists('tasks', 'idx_tasks_project_status_safe');
        $this->dropIndexIfExists('tasks', 'idx_tasks_deadline_status');
        $this->dropIndexIfExists('tasks', 'idx_tasks_project_updated');

        $this->dropIndexIfExists('project_assignments', 'idx_pa_coordinator_project');
        $this->dropIndexIfExists('project_assignments', 'idx_pa_project_role_revoked_assigned');

        $this->dropIndexIfExists('projects', 'idx_projects_priority_deadline_created');
        $this->dropIndexIfExists('projects', 'idx_projects_status_deadline');
        $this->dropIndexIfExists('projects', 'idx_projects_updated_at');
        $this->dropIndexIfExists('projects', 'idx_projects_created_by_id');
    }
};
