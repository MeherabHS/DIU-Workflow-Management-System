<?php

namespace Tests\Unit;

use App\Support\ProjectStatus;
use PHPUnit\Framework\TestCase;

class ProjectStatusTest extends TestCase
{
    public function test_canonical_project_statuses_are_current_values(): void
    {
        $this->assertSame(
            ['planned', 'in_progress', 'submitted', 'completed', 'archived', 'cancelled'],
            ProjectStatus::canonical()
        );
    }

    public function test_legacy_statuses_normalize_and_label_for_display(): void
    {
        $this->assertSame(['active', 'archive_pending'], ProjectStatus::legacy());
        $this->assertSame('in_progress', ProjectStatus::normalize('active'));
        $this->assertSame('completed', ProjectStatus::normalize('archive_pending'));
        $this->assertSame('In Progress', ProjectStatus::label('active'));
        $this->assertSame('Completed', ProjectStatus::label('archive_pending'));
    }

    public function test_due_eligible_statuses_exclude_closed_statuses(): void
    {
        $this->assertSame(['completed', 'archived', 'cancelled'], ProjectStatus::closedStatuses());
        $this->assertSame(['planned', 'in_progress', 'active'], ProjectStatus::dueEligibleStatuses());
        $this->assertSame(['planned', 'in_progress', 'active'], ProjectStatus::deadlineOpenStatuses());
        $this->assertEmpty(array_intersect(ProjectStatus::dueEligibleStatuses(), ProjectStatus::closedStatuses()));
    }

    public function test_dashboard_status_methods_preserve_existing_behavior(): void
    {
        $this->assertSame(['in_progress', 'submitted'], ProjectStatus::dashboardInProgressStatuses());
        $this->assertSame(['in_progress', 'submitted', 'active'], ProjectStatus::dashboardDonutInProgressStatuses());
        $this->assertSame(['in_progress', 'submitted', 'completed'], ProjectStatus::totalProjectKpiStatuses());
    }

    public function test_repository_status_mapping_preserves_ongoing_value(): void
    {
        $this->assertSame('ongoing', ProjectStatus::repositoryStatusForProjectStatus('in_progress'));
        $this->assertSame('ongoing', ProjectStatus::repositoryStatusForProjectStatus('active'));
        $this->assertSame('completed', ProjectStatus::repositoryStatusForProjectStatus('archive_pending'));
        $this->assertSame('submitted', ProjectStatus::repositoryStatusForProjectStatus('submitted'));
    }
}
