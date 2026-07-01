<?php

namespace App\Observers;

use App\Helpers\CacheHelper;
use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\User;

class ProjectObserver
{
    public function updated(Project $project): void
    {
        if (! $project->isDirty('status')) {
            return;
        }

        $repositoryStatus = match ($project->status) {
            'active' => 'ongoing',
            'archive_pending' => 'completed',
            default => $project->status,
        };

        RepositoryEntry::where('project_id', $project->id)
            ->whereNull('finalized_at')
            ->update(['status' => $repositoryStatus]);

        $this->clearDashboardCache($project);
    }

    public function created(Project $project): void
    {
        $this->clearDashboardCache($project);
    }

    public function deleted(Project $project): void
    {
        $this->clearDashboardCache($project);
    }

    protected function clearDashboardCache(Project $project): void
    {
        $userIds = User::where('is_active', true)
            ->whereHas('roles')
            ->pluck('id')
            ->all();

        CacheHelper::forgetDashboardForUsers($userIds);
    }
}
