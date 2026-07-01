<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_entries', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('final_status_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('repository_entries', function (Blueprint $table) {
            $table->dropForeign(['finalized_by']);
            $table->dropColumn(['finalized_at', 'finalized_by', 'final_status_snapshot']);
        });
    }
};
