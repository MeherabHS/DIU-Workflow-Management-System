<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            $table->index(['coordinator_id', 'assignment_role', 'revoked_at'], 'idx_coordinator_active');
        });

        Schema::table('subtask_assignments', function (Blueprint $table) {
            $table->index(['subordinate_id', 'revoked_at'], 'idx_subordinate_active');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->index(['project_id', 'status'], 'idx_project_status');
        });

        Schema::table('subtasks', function (Blueprint $table) {
            $table->index(['project_id', 'status'], 'idx_project_status');
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'idx_user_read');
        });

        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            $table->index(['entity_type', 'entity_id'], 'idx_entity');
        });
    }

    public function down(): void
    {
        Schema::table('project_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_coordinator_active');
        });

        Schema::table('subtask_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_subordinate_active');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('idx_project_status');
        });

        Schema::table('subtasks', function (Blueprint $table) {
            $table->dropIndex('idx_project_status');
        });

        Schema::table('workflow_notifications', function (Blueprint $table) {
            $table->dropIndex('idx_user_read');
        });

        Schema::table('workflow_audit_logs', function (Blueprint $table) {
            $table->dropIndex('idx_entity');
        });
    }
};
