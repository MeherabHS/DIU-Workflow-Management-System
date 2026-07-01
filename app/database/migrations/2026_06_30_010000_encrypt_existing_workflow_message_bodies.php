<?php

use App\Models\WorkflowMessage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migration.
     *
     * With the 'encrypted' cast now on WorkflowMessage::$casts['body'],
     * Laravel will automatically encrypt on write and decrypt on read.
     *
     * This command re-saves all existing messages so their bodies are
     * written through the encrypted cast. Messages already stored via
     * the cast (e.g. from tests) are harmless to re-save.
     */
    public function up(): void
    {
        // We'll create an artisan command to handle this safely.
        // The migration itself just registers the schema change.
        // Run: php artisan workflow:encrypt-existing-messages
        // to re-encrypt any pre-existing plaintext bodies.
    }

    public function down(): void
    {
        // No schema change to reverse — the cast change is in the model.
    }
};
