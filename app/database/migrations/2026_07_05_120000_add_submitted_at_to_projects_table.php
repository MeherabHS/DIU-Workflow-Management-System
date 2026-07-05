<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects') || Schema::hasColumn('projects', 'submitted_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('submitted_at')->nullable()->after('deadline')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('projects') || ! Schema::hasColumn('projects', 'submitted_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropIndex(['submitted_at']);
            $table->dropColumn('submitted_at');
        });
    }
};