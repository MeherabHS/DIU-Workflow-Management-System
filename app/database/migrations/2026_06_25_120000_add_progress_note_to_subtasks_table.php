<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtasks', function (Blueprint $table): void {
            $table->text('progress_note')->nullable()->after('deadline');
        });
    }

    public function down(): void
    {
        Schema::table('subtasks', function (Blueprint $table): void {
            $table->dropColumn('progress_note');
        });
    }
};
