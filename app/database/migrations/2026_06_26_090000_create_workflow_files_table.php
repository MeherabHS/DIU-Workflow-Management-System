<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subtask_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('repository_entry_id')->nullable()->constrained('repository_entries')->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('file_category')->default('attachment');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'created_at']);
            $table->index(['task_id', 'created_at']);
            $table->index(['subtask_id', 'created_at']);
            $table->index(['repository_entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_files');
    }
};
