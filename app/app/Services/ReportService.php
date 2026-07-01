<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\SubtaskAssignment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function __construct(protected ?Collection $scopedProjectIds = null)
    {
    }

    protected function applyProjectScope($query)
    {
        if ($this->scopedProjectIds !== null) {
            return $query->whereIn('id', $this->scopedProjectIds);
        }
        return $query;
    }

    // ── 1. Project Progress Report ──────────────────────────────────
    public function getProjectProgressReport(): array
    {
        $query = Project::query()
            ->with(['department', 'activePrimaryAssignment.coordinator'])
            ->withCount(['tasks', 'subtasks'])
            ->withCount(['tasks as completed_task_count' => fn ($q) => $q->whereIn('status', ['completed', 'approved'])])
            ->withCount(['subtasks as completed_work_item_count' => fn ($q) => $q->whereIn('status', ['completed', 'approved'])]);
        $this->applyProjectScope($query);
        $projects = $query->orderBy('created_at', 'desc')->get();

        $rows = $projects->map(fn ($p) => [
            'id' => $p->id,
            'title' => $p->title,
            'status' => $p->status,
            'priority' => $p->priority,
            'department' => $p->department?->name,
            'coordinator' => $p->activePrimaryAssignment?->coordinator?->name,
            'deadline' => $p->deadline?->format('Y-m-d'),
            'task_count' => $p->tasks_count,
            'completed_task_count' => $p->completed_task_count,
            'work_item_count' => $p->subtasks_count,
            'completed_work_item_count' => $p->completed_work_item_count,
        ])->values()->all();

        return [
            'total' => count($rows),
            'by_status' => $projects->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
            'rows' => $rows,
        ];
    }

    // ─ 2. Task / Work Item Status Report ──────────────────────────
    public function getTaskStatusReport(): array
    {
        // Tasks
        $taskQuery = Task::query()->with(['project', 'creator'])->withCount('subtasks');
        $this->applyProjectScope($taskQuery);
        $tasks = $taskQuery->orderBy('created_at', 'desc')->get();

        $taskRows = $tasks->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'status' => $t->status,
            'priority' => $t->priority,
            'project' => $t->project?->title,
            'creator' => $t->creator?->name,
            'deadline' => $t->deadline?->format('Y-m-d'),
            'subtask_count' => $t->subtasks_count,
        ])->values()->all();

        // Work Items (Subtasks)
        $subtaskQuery = Subtask::query()->with(['task', 'project']);
        $this->applyProjectScope($subtaskQuery);
        $subtasks = $subtaskQuery->orderBy('created_at', 'desc')->get();

        $subtaskRows = $subtasks->map(fn ($s) => [
            'id' => $s->id,
            'title' => $s->title,
            'status' => $s->status,
            'priority' => $s->priority,
            'task' => $s->task?->title,
            'project' => $s->project?->title,
            'deadline' => $s->deadline?->format('Y-m-d'),
        ])->values()->all();

        return [
            'tasks' => [
                'total' => count($taskRows),
                'by_status' => $tasks->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
                'rows' => $taskRows,
            ],
            'work_items' => [
                'total' => count($subtaskRows),
                'by_status' => $subtasks->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
                'rows' => $subtaskRows,
            ],
        ];
    }

    // ── 3. Coordinator Performance Report ───────────────────────────
    public function getCoordinatorPerformanceReport(): array
    {
        $coordinators = User::role('Coordinator')->select('id', 'name', 'email')->get();

        // Single aggregated query for all coordinator metrics
        $projectIdList = $this->scopedProjectIds ?? Project::pluck('id');

        $metrics = DB::table('projects')
            ->join('project_assignments', 'projects.id', '=', 'project_assignments.project_id')
            ->leftJoin('tasks', 'projects.id', '=', 'tasks.project_id')
            ->leftJoin('subtasks', 'projects.id', '=', 'subtasks.project_id')
            ->whereIn('projects.id', $projectIdList)
            ->where('project_assignments.assignment_role', 'primary')
            ->whereNull('project_assignments.revoked_at')
            ->groupBy('project_assignments.coordinator_id')
            ->selectRaw('
                project_assignments.coordinator_id,
                COUNT(DISTINCT projects.id) as active_projects,
                COUNT(DISTINCT tasks.id) as total_tasks,
                COUNT(DISTINCT CASE WHEN tasks.status IN ("completed","approved") THEN tasks.id END) as completed_tasks,
                COUNT(DISTINCT subtasks.id) as total_work_items,
                COUNT(DISTINCT CASE WHEN subtasks.status IN ("completed","approved") THEN subtasks.id END) as completed_work_items
            ')
            ->get()
            ->keyBy('coordinator_id');

        $rows = $coordinators->map(function ($coord) use ($metrics) {
            $m = $metrics->get($coord->id);
            $totalTasks = $m?->total_tasks ?? 0;
            $completedTasks = $m?->completed_tasks ?? 0;
            $totalWorkItems = $m?->total_work_items ?? 0;
            $completedWorkItems = $m?->completed_work_items ?? 0;

            return [
                'id' => $coord->id,
                'name' => $coord->name,
                'email' => $coord->email,
                'active_projects' => $m?->active_projects ?? 0,
                'total_tasks' => $totalTasks,
                'completed_tasks' => $completedTasks,
                'task_completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0,
                'total_work_items' => $totalWorkItems,
                'completed_work_items' => $completedWorkItems,
                'work_item_completion_rate' => $totalWorkItems > 0 ? round(($completedWorkItems / $totalWorkItems) * 100, 1) : 0,
            ];
        })->values()->all();

        return ['rows' => $rows];
    }

    // ── 4. Subordinate Work Completion Report ───────────────────────
    public function getSubordinateCompletionReport(): array
    {
        $subordinates = User::role('Subordinate')->select('id', 'name', 'email')->get();

        // Single aggregated query
        $metrics = DB::table('subtask_assignments')
            ->join('subtasks', 'subtask_assignments.subtask_id', '=', 'subtasks.id')
            ->whereNull('subtask_assignments.revoked_at')
            ->groupBy('subtask_assignments.subordinate_id')
            ->selectRaw('
                subtask_assignments.subordinate_id,
                COUNT(*) as total_assigned,
                SUM(CASE WHEN subtasks.status = "completed" THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN subtasks.status = "in_progress" THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN subtasks.status = "pending" THEN 1 ELSE 0 END) as pending
            ')
            ->get()
            ->keyBy('subordinate_id');

        $rows = $subordinates->map(function ($sub) use ($metrics) {
            $m = $metrics->get($sub->id);
            $total = $m?->total_assigned ?? 0;
            $completed = $m?->completed ?? 0;

            return [
                'id' => $sub->id,
                'name' => $sub->name,
                'email' => $sub->email,
                'total_assigned' => $total,
                'completed' => $completed,
                'in_progress' => $m?->in_progress ?? 0,
                'pending' => $m?->pending ?? 0,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->values()->all();

        return ['rows' => $rows];
    }

    // ── 5. Repository Preservation Report ───────────────────────────
    public function getRepositoryPreservationReport(): array
    {
        $query = RepositoryEntry::query()
            ->with(['project', 'department', 'creator', 'finalizedBy'])
            ->withCount(['updates', 'files']);
        if ($this->scopedProjectIds !== null) {
            $query->whereIn('project_id', $this->scopedProjectIds);
        }
        $entries = $query->orderBy('created_at', 'desc')->get();

        $rows = $entries->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'type' => $e->type,
            'status' => $e->status,
            'project' => $e->project?->title,
            'department' => $e->department?->name,
            'created_by' => $e->creator?->name,
            'finalized_at' => $e->finalized_at?->format('Y-m-d'),
            'finalized_by' => $e->finalizedBy?->name,
            'update_count' => $e->updates_count,
            'file_count' => $e->files_count,
        ])->values()->all();

        return [
            'total' => count($rows),
            'by_status' => $entries->groupBy('status')->map(fn ($g) => $g->count())->toArray(),
            'rows' => $rows,
        ];
    }

    // ── 6. Audit Activity Report ───────────────────────────────────
    public function getAuditActivityReport(): array
    {
        $query = AuditLog::query()->with(['actor', 'project']);
        if ($this->scopedProjectIds !== null) {
            $query->whereIn('project_id', $this->scopedProjectIds);
        }
        $logs = $query->orderBy('created_at', 'desc')->limit(200)->get();

        $rows = $logs->map(fn ($l) => [
            'id' => $l->id,
            'action' => $l->action,
            'actor' => $l->actor?->name,
            'entity_type' => $l->entity_type,
            'project' => $l->project?->title,
            'ip_address' => $l->ip_address,
            'created_at' => $l->created_at?->format('Y-m-d H:i:s'),
        ])->values()->all();

        return [
            'total' => $logs->count(),
            'by_action' => $logs->groupBy('action')->map(fn ($g) => $g->count())->toArray(),
            'rows' => $rows,
        ];
    }

    // ── CSV Export ──────────────────────────────────────────────────
    public function exportToCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            fputcsv($output, array_values($row));
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        return $csv;
    }
}
