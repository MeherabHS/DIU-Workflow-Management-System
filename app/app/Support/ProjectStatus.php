<?php

namespace App\Support;

class ProjectStatus
{
    public static function canonical(): array
    {
        return ['planned', 'in_progress', 'submitted', 'completed', 'archived', 'cancelled'];
    }

    public static function legacy(): array
    {
        return ['active', 'archive_pending'];
    }

    public static function activeWorkStatuses(): array
    {
        return ['planned', 'in_progress', 'submitted', 'active'];
    }

    public static function closedStatuses(): array
    {
        return ['completed', 'archived', 'cancelled'];
    }

    public static function dueEligibleStatuses(): array
    {
        return self::deadlineOpenStatuses();
    }

    public static function deadlineOpenStatuses(): array
    {
        return ['planned', 'in_progress', 'active'];
    }

    public static function dashboardInProgressStatuses(): array
    {
        return ['in_progress', 'submitted'];
    }

    public static function dashboardDonutInProgressStatuses(): array
    {
        return ['in_progress', 'submitted', 'active'];
    }

    public static function totalProjectKpiStatuses(): array
    {
        return ['in_progress', 'submitted', 'completed'];
    }

    public static function repositoryStatuses(): array
    {
        return ['planned', 'ongoing', 'submitted', 'completed', 'archived', 'cancelled'];
    }

    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);

        return match ($status) {
            '' => 'planned',
            'active' => 'in_progress',
            'archive_pending' => 'completed',
            default => $status,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            'planned' => 'Planned',
            'in_progress' => 'In Progress',
            'submitted' => 'Submitted',
            'completed' => 'Completed',
            'archived' => 'Archived',
            'cancelled' => 'Cancelled',
            'active' => 'In Progress',
            'archive_pending' => 'Completed',
            'ongoing' => 'Ongoing',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function repositoryStatusForProjectStatus(string $status): string
    {
        return match ($status) {
            'in_progress', 'active' => 'ongoing',
            'archive_pending' => 'completed',
            default => $status,
        };
    }
}
