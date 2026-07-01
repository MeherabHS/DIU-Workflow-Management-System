<?php

namespace App\Console\Commands;

use App\Models\WorkflowMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

class EncryptExistingWorkflowMessages extends Command
{
    protected $signature = 'workflow:encrypt-existing-messages';
    protected $description = 'Re-encrypt existing plaintext workflow message bodies using the encrypted cast';

    public function handle(): int
    {
        $this->info('Checking for existing workflow messages...');

        $total = WorkflowMessage::count();

        if ($total === 0) {
            $this->info('No workflow messages found. Nothing to encrypt.');

            return Command::SUCCESS;
        }

        $this->info("Found {$total} workflow messages. Re-saving to encrypt bodies...");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $encrypted = 0;
        $alreadyEncrypted = 0;
        $errors = 0;

        WorkflowMessage::query()->each(function (WorkflowMessage $message) use ($bar, &$encrypted, &$alreadyEncrypted, &$errors): void {
            try {
                // Read the raw attribute to avoid triggering the decrypt accessor.
                $rawBody = $message->getAttributes()['body'] ?? '';

                if ($rawBody === '' || $rawBody === null) {
                    // Nothing to encrypt — skip empty bodies.
                } elseif ($this->looksEncrypted($rawBody)) {
                    $alreadyEncrypted++;
                } else {
                    // Re-assign through the accessor so it encrypts on save.
                    $message->body = $rawBody;
                    $message->save();
                    $encrypted++;
                }
            } catch (\Exception $e) {
                $this->warn("Failed to encrypt message #{$message->id}: {$e->getMessage()}");
                $errors++;
            }

            $bar->advance();
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Done! Encrypted: {$encrypted}, Already encrypted: {$alreadyEncrypted}, Errors: {$errors}");

        return Command::SUCCESS;
    }

    /**
     * Check if a value looks like an already-encrypted string.
     * Laravel's Crypt::encryptString produces a JSON string starting with '{"iv":'.
     */
    protected function looksEncrypted(string $value): bool
    {
        return str_starts_with($value, 'eyJ') || str_starts_with($value, '{"iv":');
    }
}
