<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subtask_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('message_type')->default('message')->index();
            $table->text('body');
            $table->string('visibility')->default('thread')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'task_id', 'subtask_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_messages');
    }
};
