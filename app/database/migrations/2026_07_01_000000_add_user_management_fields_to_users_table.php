<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index()->after('email_verified_at');
            $table->foreignId('department_id')->nullable()->after('is_active')->constrained()->nullOnDelete();
            $table->string('designation')->nullable()->after('department_id');
            $table->string('phone')->nullable()->after('designation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['is_active', 'department_id', 'designation', 'phone']);
        });
    }
};
