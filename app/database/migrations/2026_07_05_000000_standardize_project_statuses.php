<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Projects: active → in_progress
        DB::table('projects')
            ->where('status', 'active')
            ->update(['status' => 'in_progress']);

        // Projects: archive_pending → completed
        DB::table('projects')
            ->where('status', 'archive_pending')
            ->update(['status' => 'completed']);

        // Repository entries: active → ongoing
        DB::table('repository_entries')
            ->where('status', 'active')
            ->update(['status' => 'ongoing']);

        // Repository entries: archive_pending → completed
        DB::table('repository_entries')
            ->where('status', 'archive_pending')
            ->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // Projects: in_progress → active
        DB::table('projects')
            ->where('status', 'in_progress')
            ->update(['status' => 'active']);

        // Projects: completed records that were originally archive_pending
        // cannot be reliably reversed, so leave as-is

        // Repository entries: ongoing → active
        DB::table('repository_entries')
            ->where('status', 'ongoing')
            ->update(['status' => 'active']);
    }
};
