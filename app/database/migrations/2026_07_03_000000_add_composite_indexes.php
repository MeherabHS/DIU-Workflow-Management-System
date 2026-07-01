<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check whether an index already exists on the given table.
     * Safe for both PostgreSQL and MySQL.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            $result = DB::selectOne(
                "SELECT to_regclass(?) AS regclass",
                ["public.{$indexName}"]
            );
            return $result && $result->regclass !== null;
        }

        // MySQL / MariaDB
        $result = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return count($result) > 0;
    }

    public function up(): void
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            if (! $this->indexExists('project_assignments', 'idx_project_assignments_coordinator_active')) {
                $table->index(
                    ['coordinator_id', 'assignment_role', 'revoked_at'],
                    'idx_project_assignments_coordinator_active'
                );
            }
        });

        Schema::table('subtask_assignments', function (Blueprint $table) {
            if (! $this->indexExists('subtask_assignments', 'idx_subtask_assignments_subordinate_active')) {
                $table->index(
                    ['subordinate_id', 'revoked_at'],
                    'idx_subtask_assignments_subordinate_active'
                );
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if (! $this->indexExists('tasks', 'idx_tasks_project_status')) {
                $table->index(
                    ['project_id', 'status'],
                    'idx_tasks_project_status'
                );
            }
        });

        Schema::table('subtasks', function (Blueprint $table) {
            if (! $this->indexExists('subtasks', 'idx_subtasks_project_status')) {
                $table->index(
                    ['project_id', 'status'],
                    'idx_subtasks_project_status'
                );
            }
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            if (! $this->indexExists('workflow_notifications', 'idx_workflow_notifications_user_read')) {
                $table->index(
                    ['user_id', 'read_at'],
                    'idx_workflow_notifications_user_read'
                );
            }
        });

        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            if (! $this->indexExists('workflow_audit_logs', 'idx_workflow_audit_logs_entity')) {
                $table->index(
                    ['entity_type', 'entity_id'],
                    'idx_workflow_audit_logs_entity'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            if ($this->indexExists('project_assignments', 'idx_project_assignments_coordinator_active')) {
                $table->dropIndex('idx_project_assignments_coordinator_active');
            }
        });

        Schema::table('subtask_assignments', function (Blueprint $table) {
            if ($this->indexExists('subtask_assignments', 'idx_subtask_assignments_subordinate_active')) {
                $table->dropIndex('idx_subtask_assignments_subordinate_active');
            }
        });

        Schema::table('tasks', function (Blueprint $table) {
            if ($this->indexExists('tasks', 'idx_tasks_project_status')) {
                $table->dropIndex('idx_tasks_project_status');
            }
        });

        Schema::table('subtasks', function (Blueprint $table) {
            if ($this->indexExists('subtasks', 'idx_subtasks_project_status')) {
                $table->dropIndex('idx_subtasks_project_status');
            }
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            if ($this->indexExists('workflow_notifications', 'idx_workflow_notifications_user_read')) {
                $table->dropIndex('idx_workflow_notifications_user_read');
            }
        });

        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            if ($this->indexExists('workflow_audit_logs', 'idx_workflow_audit_logs_entity')) {
                $table->dropIndex('idx_workflow_audit_logs_entity');
            }
        });
    }
};
