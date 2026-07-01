<?php

namespace App\Console\Commands;

use App\Models\WorkflowNotification;
use Illuminate\Console\Command;

class NormalizeNotificationActionUrls extends Command
{
    protected $signature = 'notifications:normalize-action-urls';
    protected $description = 'Convert absolute action_url values (localhost/127.0.0.1) to relative paths in workflow_notifications';

    public function handle(): int
    {
        $converted = 0;
        $skipped = 0;

        WorkflowNotification::query()
            ->whereNotNull('action_url')
            ->chunkById(100, function ($notifications) use (&$converted, &$skipped): void {
                foreach ($notifications as $notification) {
                    $url = $notification->action_url;

                    // Only convert URLs that start with http:// or https://
                    if (! preg_match('#^https?://#i', $url)) {
                        $skipped++;
                        continue;
                    }

                    // Extract the path component
                    $path = parse_url($url, PHP_URL_PATH);

                    if ($path === false || $path === null) {
                        $skipped++;
                        continue;
                    }

                    // Append query string if present
                    $query = parse_url($url, PHP_URL_QUERY);
                    if ($query) {
                        $path .= '?'.$query;
                    }

                    $notification->update(['action_url' => $path]);
                    $converted++;
                }
            });

        $this->info("Done. Converted: {$converted}, Skipped (already relative or external): {$skipped}");

        return Command::SUCCESS;
    }
}
