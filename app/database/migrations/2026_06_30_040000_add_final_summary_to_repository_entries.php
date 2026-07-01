<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_entries', function (Blueprint $table) {
            $table->text('final_summary')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('repository_entries', function (Blueprint $table) {
            $table->dropColumn('final_summary');
        });
    }
};
