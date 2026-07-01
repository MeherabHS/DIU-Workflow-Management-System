<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_comparison_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subtask_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_config_id')->constrained('workflow_comparison_configs')->cascadeOnDelete();
            $table->foreignId('workflow_file_id')->nullable()->constrained('workflow_files')->nullOnDelete();
            $table->text('requirement_text');
            $table->integer('source_page')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_deliverables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_config_id')->constrained('workflow_comparison_configs')->cascadeOnDelete();
            $table->foreignId('workflow_file_id')->nullable()->constrained('workflow_files')->nullOnDelete();
            $table->text('deliverable_text');
            $table->integer('source_page')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_comparison_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comparison_config_id')->constrained('workflow_comparison_configs')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('workflow_requirements')->nullOnDelete();
            $table->foreignId('deliverable_id')->nullable()->constrained('workflow_deliverables')->nullOnDelete();
            $table->string('status')->default('pending'); // completed, partially_completed, missing, unclear, config_missing, failed, pending
            $table->json('matched_items')->nullable();
            $table->decimal('completion_percentage', 5, 2)->default(0);
            $table->text('summary')->nullable();
            $table->longText('raw_ai_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_comparison_results');
        Schema::dropIfExists('workflow_deliverables');
        Schema::dropIfExists('workflow_requirements');
        Schema::dropIfExists('workflow_comparison_configs');
    }
};
