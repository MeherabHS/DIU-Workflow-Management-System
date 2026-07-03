<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Support\ProjectStatus;
use Illuminate\Console\Command;

class BackfillRepositoryEntries extends Command
{
    protected $signature = 'repository:backfill';
    protected $description = 'Create RepositoryEntry records for all existing projects that lack one';

    public function handle(): int
    {
        $created = 0;
        $skipped = 0;

        $this->info('Scanning projects for missing repository entries...');

        Project::query()->chunkById(100, function ($projects) use (&$created, &$skipped): void {
            foreach ($projects as $project) {
                // Skip if a repository entry already exists for this project
                $exists = RepositoryEntry::where('project_id', $project->id)->exists();
                if ($exists) {
                    $skipped++;
                    continue;
                }

                $repositoryStatus = ProjectStatus::repositoryStatusForProjectStatus($project->status ?? 'planned');

                RepositoryEntry::create([
                    'project_id' => $project->id,
                    'title' => $project->title,
                    'description' => $project->description,
                    'department_id' => $project->department_id,
                    'status' => $repositoryStatus,
                    'created_by' => $project->created_by,
                    'value_currency' => 'BDT',
                ]);

                $created++;
            }
        });

        $this->info("Done. Created: {$created}, Skipped (already had entry): {$skipped}");

        return Command::SUCCESS;
    }
}
