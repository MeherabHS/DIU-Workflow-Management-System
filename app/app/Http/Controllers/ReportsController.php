<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    protected function getScopedProjectIds(Request $request): ?Collection
    {
        $user = $request->user();
        if ($user->hasRole('Admin')) {
            return null; // Admin sees all
        }
        if ($user->hasRole('PM/Manager')) {
            return Project::where('created_by', $user->id)->pluck('id');
        }
        // Coordinator: scoped to assigned projects
        if ($user->hasRole('Coordinator')) {
            return Project::whereHas('assignments', fn ($q) => $q
                ->where('coordinator_id', $user->id)
                ->where('assignment_role', 'primary')
                ->whereNull('revoked_at')
            )->pluck('id');
        }
        return collect(); // Subordinate: no report access
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view reports'), 403);

        return Inertia::render('Reports/Index', [
            'pageTitle' => 'Reports & Export',
            'pageSubtitle' => 'Generate reports and export data for projects, tasks, and workflow activities.',
            'canExport' => $request->user()->can('export reports'),
            'reports' => [
                ['key' => 'project-progress', 'title' => 'Project Progress Report', 'description' => 'Overview of all projects with status, task counts, and completion rates.'],
                ['key' => 'task-status', 'title' => 'Task / Work Item Status Report', 'description' => 'Detailed status breakdown of tasks and work items.'],
                ['key' => 'coordinator-performance', 'title' => 'Coordinator Performance Report', 'description' => 'Performance metrics for all coordinators.'],
                ['key' => 'subordinate-completion', 'title' => 'Subordinate Work Completion Report', 'description' => 'Work completion rates for all subordinates.'],
                ['key' => 'repository-preservation', 'title' => 'Repository Preservation Report', 'description' => 'Repository entries and their preservation status.'],
                ['key' => 'audit-activity', 'title' => 'Audit Activity Report', 'description' => 'Recent system audit log activities.'],
            ],
        ]);
    }

    public function projectProgress(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getProjectProgressReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            return $this->csvResponse('project-progress-report.csv',
                ['Title', 'Status', 'Priority', 'Department', 'Coordinator', 'Deadline', 'Tasks', 'Completed Tasks', 'Work Items', 'Completed Work Items'],
                array_map(fn ($r) => [$r['title'], $r['status'], $r['priority'] ?? '', $r['department'] ?? '', $r['coordinator'] ?? '', $r['deadline'] ?? '', $r['task_count'], $r['completed_task_count'], $r['work_item_count'], $r['completed_work_item_count']], $data['rows'])
            );
        }

        return Inertia::render('Reports/ProjectProgress', [
            'pageTitle' => 'Project Progress Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    public function taskStatus(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getTaskStatusReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            $combinedRows = [];
            foreach ($data['tasks']['rows'] as $r) {
                $combinedRows[] = ['Task', $r['title'], $r['status'], $r['project'] ?? '', $r['deadline'] ?? ''];
            }
            foreach ($data['work_items']['rows'] as $r) {
                $combinedRows[] = ['Work Item', $r['title'], $r['status'], $r['project'] ?? '', $r['deadline'] ?? ''];
            }
            return $this->csvResponse('task-status-report.csv',
                ['Type', 'Title', 'Status', 'Project', 'Deadline'],
                $combinedRows
            );
        }

        return Inertia::render('Reports/TaskStatus', [
            'pageTitle' => 'Task / Work Item Status Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    public function coordinatorPerformance(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getCoordinatorPerformanceReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            return $this->csvResponse('coordinator-performance-report.csv',
                ['Name', 'Email', 'Active Projects', 'Total Tasks', 'Completed Tasks', 'Task %', 'Total Work Items', 'Completed Work Items', 'Work Item %'],
                array_map(fn ($r) => [$r['name'], $r['email'], $r['active_projects'], $r['total_tasks'], $r['completed_tasks'], $r['task_completion_rate'], $r['total_work_items'], $r['completed_work_items'], $r['work_item_completion_rate']], $data['rows'])
            );
        }

        return Inertia::render('Reports/CoordinatorPerformance', [
            'pageTitle' => 'Coordinator Performance Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    public function subordinateCompletion(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getSubordinateCompletionReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            return $this->csvResponse('subordinate-completion-report.csv',
                ['Name', 'Email', 'Total Assigned', 'Completed', 'In Progress', 'Pending', 'Completion %'],
                array_map(fn ($r) => [$r['name'], $r['email'], $r['total_assigned'], $r['completed'], $r['in_progress'], $r['pending'], $r['completion_rate']], $data['rows'])
            );
        }

        return Inertia::render('Reports/SubordinateCompletion', [
            'pageTitle' => 'Subordinate Work Completion Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    public function repositoryPreservation(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getRepositoryPreservationReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            return $this->csvResponse('repository-preservation-report.csv',
                ['Title', 'Type', 'Status', 'Project', 'Department', 'Created By', 'Finalized At', 'Finalized By', 'Updates', 'Files'],
                array_map(fn ($r) => [$r['title'], $r['type'] ?? '', $r['status'], $r['project'] ?? '', $r['department'] ?? '', $r['created_by'] ?? '', $r['finalized_at'] ?? '', $r['finalized_by'] ?? '', $r['update_count'], $r['file_count']], $data['rows'])
            );
        }

        return Inertia::render('Reports/RepositoryPreservation', [
            'pageTitle' => 'Repository Preservation Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    public function auditActivity(Request $request, ReportService $reportService): Response|StreamedResponse
    {
        abort_unless($request->user()->can('view reports'), 403);
        $scopedIds = $this->getScopedProjectIds($request);
        $service = new ReportService($scopedIds);
        $data = $service->getAuditActivityReport();

        if ($request->query('export') === 'csv' && $request->user()->can('export reports')) {
            return $this->csvResponse('audit-activity-report.csv',
                ['Action', 'Actor', 'Entity Type', 'Project', 'IP Address', 'Timestamp'],
                array_map(fn ($r) => [$r['action'], $r['actor'] ?? '', $r['entity_type'] ?? '', $r['project'] ?? '', $r['ip_address'] ?? '', $r['created_at']], $data['rows'])
            );
        }

        return Inertia::render('Reports/AuditActivity', [
            'pageTitle' => 'Audit Activity Report',
            ...$data,
            'canExport' => $request->user()->can('export reports'),
        ]);
    }

    protected function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
