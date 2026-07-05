<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Controllers\Concerns\ProvidesWorkflowMessages;
use App\Http\Requests\AssignCoordinatorRequest;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectAssignment;
use App\Models\RepositoryEntry;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowComparisonConfig;
use App\Models\WorkflowComparisonResult;
use App\Models\WorkflowFile;
use App\Services\AuditLogService;
use App\Services\WorkflowFileService;
use App\Services\WorkflowNotificationService;
use App\Support\ProjectStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ProvidesWorkflowFiles, ProvidesWorkflowMessages;

    public function __construct(protected AuditLogService $audit)
    {
    }
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Project::class);

        // Validate search/filter inputs
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ProjectStatus::canonical())],
        ]);

        $query = Project::query()
            ->with(['department', 'creator', 'activePrimaryAssignment.coordinator']);

        if ($request->filled('search')) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $request->string('search')->trim());
            $query->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhereHas('activePrimaryAssignment.coordinator', function ($cq) use ($search): void {
                        $cq->where('name', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->trim());
        }

        return Inertia::render('Projects/Index', [
            'pageTitle' => 'Projects',
            'pageSubtitle' => 'Create projects, assign coordinators, and track ownership.',
            'primaryAction' => 'Create Project',
            'visibleActions' => ['Create Project', 'View', 'Edit', 'Assign Coordinator', 'View Tasks'],
            'projects' => $query->latest('updated_at')->paginate(10)->withQueryString(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('Projects/Form', $this->formData(new Project([
            'status' => 'planned',
        ])) + [
            'pageTitle' => 'Create Project',
            'submitLabel' => 'Create Project',
            'method' => 'post',
            'action' => route('projects.store'),
        ]);
    }

    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $this->authorize('create', Project::class);

        $validated = $request->validated();
        $uploadedFile = $validated['file'] ?? null;
        unset($validated['file']);

        $project = DB::transaction(function () use ($request, $validated, $uploadedFile) {
            $project = Project::create([
                ...$validated,
                'created_by' => $request->user()->id,
            ]);

            // Auto-create a linked repository entry so every project is preserved in Repository.
            RepositoryEntry::firstOrCreate(
                ['project_id' => $project->id],
                [
                    'title' => $project->title,
                    'description' => $project->description,
                    'department_id' => $project->department_id,
                    'status' => $this->mapProjectToRepositoryStatus($project->status ?? 'planned'),
                    'created_by' => $request->user()->id,
                    'value_currency' => 'BDT',
                ]
            );

            if ($uploadedFile) {
                $this->authorize('create', [WorkflowFile::class, $project]);
                $this->storeInitialProjectFile($request, $project, $uploadedFile);
            }

            $this->audit->logProjectCreated($project);

            return $project;
        });

        return redirect()->route('projects.show', $project)->with('status', 'Project created successfully.');
    }

    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $project->load([
            'department',
            'creator',
            'activePrimaryAssignment.coordinator',
            'assignments.coordinator',
            'assignments.assigner',
        ]);

        $user = $request->user();
        $canViewTasks = $user->can('viewProjectTasks', [Task::class, $project]);
        $canCreateTask = $user->can('createInProject', [Task::class, $project]);
        $canAssignCoordinator = $user->can('assignCoordinator', $project);
        $canUpdateProject = $user->can('update', $project);
        $alreadyFinalized = RepositoryEntry::where('project_id', $project->id)
            ->whereNotNull('finalized_at')
            ->first();
        $canSubmitForReview = $user->can('submitForReview', $project);
        $canFinalizeProject = $canUpdateProject
            && $user->can('create repository entry')
            && $project->status === 'completed'
            && $alreadyFinalized === null;
        $actions = array_values(array_filter([
            $canViewTasks ? 'View Tasks' : null,
            $canUpdateProject ? 'Edit' : null,
            $canAssignCoordinator ? 'Assign Coordinator' : null,
            $canCreateTask ? 'Create Task' : null,
        ]));

        return Inertia::render('Projects/Show', [
            'pageTitle' => 'Project Details',
            'pageSubtitle' => $project->title,
            'project' => $project,
            'canViewTasks' => $canViewTasks,
            'canCreateTask' => $canCreateTask,
            'canAssignCoordinator' => $canAssignCoordinator,
            'canUpdateProject' => $canUpdateProject,
            'canFinalizeProject' => $canFinalizeProject,
            'canSubmitForReview' => $canSubmitForReview,
            'submitForReviewUrl' => $canSubmitForReview ? route('projects.submit-for-review', $project) : null,
            'alreadyFinalized' => $alreadyFinalized
                ? [
                    'id' => $alreadyFinalized->id,
                    'route' => route('repository.show', $alreadyFinalized),
                    'finalized_at' => $alreadyFinalized->finalized_at?->toISOString(),
                    'finalized_by' => $alreadyFinalized->finalizedBy?->name,
                ]
                : null,
            'closeHref' => $user->hasRole('Coordinator') ? route('projects.mine') : route('projects.index'),
            'actions' => $actions,
            'sections' => ['Project Summary', 'Assignment History', 'Attachments', 'Feedback / Follow-up'],
            ...$this->workflowFileProps($project, $user, 'Attachments'),
            ...$this->messageThreadProps($project, $user),
            ...$this->comparisonProps($project, $user),
        ]);
    }

    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('Projects/Form', $this->formData($project) + [
            'pageTitle' => 'Edit Project',
            'submitLabel' => 'Update Project',
            'method' => 'patch',
            'action' => route('projects.update', $project),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validated();
        $changes = array_keys(array_diff_assoc($validated, $project->getOriginal()));

        if (($validated['status'] ?? null) === 'submitted' && $project->status !== 'submitted' && $project->submitted_at === null) {
            $validated['submitted_at'] = now();
        }

        $project->update($validated);

        $this->audit->logProjectUpdated($project, ['fields' => $changes]);

        return redirect()->route('projects.show', $project)->with('status', 'Project updated successfully.');
    }

    public function submitForReview(Project $project): RedirectResponse
    {
        $this->authorize('submitForReview', $project);

        $project->update([
            'status' => 'submitted',
            'submitted_at' => $project->submitted_at ?? now(),
        ]);

        $this->audit->logProjectUpdated($project, ['fields' => ['status', 'submitted_at']]);

        return redirect()->route('projects.show', $project)->with('status', 'Project submitted for PM/Admin review.');
    }
    public function editCoordinatorAssignment(Project $project): Response
    {
        $this->authorize('assignCoordinator', $project);

        $project->load(['activePrimaryAssignment.coordinator', 'assignments.coordinator', 'assignments.assigner']);

        return Inertia::render('Projects/AssignCoordinator', [
            'pageTitle' => 'Assign Coordinator',
            'project' => $project,
            'coordinators' => User::role('Coordinator')
                ->where('is_active', true)
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get(),
            'action' => route('projects.assign-coordinator.update', $project),
        ]);
    }

    public function updateCoordinatorAssignment(AssignCoordinatorRequest $request, Project $project, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('assignCoordinator', $project);

        $coordinatorId = (int) $request->validated('coordinator_id');

        /** @var ProjectAssignment|null $activeAssignment */
        $activeAssignment = $project->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->latest('assigned_at')
            ->first();

        if ($activeAssignment && (int) $activeAssignment->coordinator_id === $coordinatorId) {
            return redirect()
                ->route('projects.assign-coordinator.edit', $project)
                ->with('status', 'Coordinator already assigned.');
        }

        DB::transaction(function () use ($request, $project, $activeAssignment, $coordinatorId, $notificationService): void {
            if ($activeAssignment) {
                $oldCoordinator = $activeAssignment->coordinator;
                $activeAssignment->update(['revoked_at' => now()]);

                if ($oldCoordinator) {
                    $notificationService->notifyCoordinatorRevoked($project, $oldCoordinator, $request->user());
                }
            }

            ProjectAssignment::create([
                'project_id' => $project->id,
                'coordinator_id' => $coordinatorId,
                'assigned_by' => $request->user()->id,
                'assignment_role' => 'primary',
                'assigned_at' => now(),
                'revoked_at' => null,
            ]);

            $coordinator = User::findOrFail($coordinatorId);
            $notificationService->notifyCoordinatorAssigned($project, $coordinator, $request->user());

            $this->audit->logCoordinatorAssigned($project, $coordinator, $request->user());
        });

        return redirect()->route('projects.show', $project)->with('status', 'Coordinator assigned successfully.');
    }

    public function revokeCoordinatorAssignment(Project $project, WorkflowNotificationService $notificationService): RedirectResponse
    {
        $this->authorize('assignCoordinator', $project);

        $activeAssignment = $project->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->latest('assigned_at')
            ->first();

        if (! $activeAssignment) {
            return back()->with('status', 'No active coordinator assignment found.');
        }

        $coordinator = $activeAssignment->coordinator;
        $activeAssignment->update(['revoked_at' => now()]);

        $notificationService->notifyCoordinatorRevoked(
            $project,
            $coordinator,
            request()->user()
        );

        return back()->with('status', 'Coordinator assignment revoked successfully.');
    }

    public function finalizeToRepository(Project $project, Request $request): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless($request->user()?->can('create repository entry'), 403);

        if ($project->status !== 'completed') {
            return back()->with('error', 'Only completed projects can be finalized to Repository.');
        }

        $alreadyFinalized = RepositoryEntry::where('project_id', $project->id)
            ->whereNotNull('finalized_at')
            ->exists();

        abort_if($alreadyFinalized, 409, 'This project has already been finalized to Repository.');

        $coordinator = $project->activePrimaryAssignment?->coordinator;
        $comparisonSummary = $this->getComparisonSummary($project);
        $taskCount = $project->tasks()->count();
        $approvedTaskCount = $project->tasks()->whereNotNull('approved_at')->count();
        $subtaskCount = $project->subtasks()->count();
        $approvedSubtaskCount = $project->subtasks()->whereNotNull('approved_at')->count();
        $fileCount = $project->files()->count();

        // Review summary: count workflow file categories as a proxy for review evidence
        $reviewSummary = $this->getReviewSummary($project);

        $snapshot = [
            'project' => [
                'title' => $project->title,
                'status' => $project->status,
                'priority' => $project->priority,
                'deadline' => $project->deadline?->format('Y-m-d'),
                'completed_at' => $project->completed_at?->toISOString(),
            ],
            'active_coordinator' => $coordinator?->name,
            'task_count' => $taskCount,
            'approved_task_count' => $approvedTaskCount,
            'work_item_count' => $subtaskCount,
            'approved_work_item_count' => $approvedSubtaskCount,
            'file_count' => $fileCount,
            'ai_comparison_summary' => $comparisonSummary,
            'review_summary' => $reviewSummary,
        ];

        $finalSummary = $request->input('final_summary');
        if (blank($finalSummary)) {
            $finalSummary = sprintf(
                'Project "%s" finalized as %s. Priority: %s. %d tasks (%d approved), %d work items (%d approved), %d files attached.',
                $project->title,
                $project->status,
                $project->priority ?? 'N/A',
                $taskCount,
                $approvedTaskCount,
                $subtaskCount,
                $approvedSubtaskCount,
                $fileCount
            );
        }

        $entry = RepositoryEntry::where('project_id', $project->id)
            ->whereNull('finalized_at')
            ->first();
        $now = now();

        if ($entry) {
            $entry->update([
                'final_summary' => $finalSummary,
                'department_id' => $entry->department_id ?? $project->department_id,
                'responsible_user_id' => $entry->responsible_user_id ?? $coordinator?->id,
                'status' => 'completed',
                'completed_at' => $entry->completed_at ?? $now,
                'finalized_at' => $now,
                'finalized_by' => $request->user()->id,
                'value_currency' => $entry->value_currency ?: 'BDT',
                'final_status_snapshot' => $snapshot,
            ]);
        } else {
            $entry = RepositoryEntry::create([
                'project_id' => $project->id,
                'title' => $project->title,
                'description' => $project->description,
                'final_summary' => $finalSummary,
                'department_id' => $project->department_id,
                'responsible_user_id' => $coordinator?->id,
                'status' => 'completed',
                'completed_at' => $now,
                'finalized_at' => $now,
                'finalized_by' => $request->user()->id,
                'created_by' => $request->user()->id,
                'value_currency' => 'BDT',
                'final_status_snapshot' => $snapshot,
            ]);
        }

        // Link project-level workflow files to the repository entry (no duplication)
        $project->files()
            ->whereNull('repository_entry_id')
            ->update(['repository_entry_id' => $entry->id]);

        return redirect()->route('repository.show', $entry)
            ->with('status', 'Project finalized to Repository successfully.');
    }

    protected function getReviewSummary(Project $project): array
    {
        $files = $project->files;

        $categoryCounts = [];
        foreach ($files as $file) {
            $cat = $file->file_category ?? 'uncategorized';
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }

        $latestFile = $files->sortByDesc('created_at')->first();

        return [
            'total_files' => $files->count(),
            'files_by_category' => $categoryCounts,
            'latest_file' => $latestFile
                ? [
                    'name' => $latestFile->original_name,
                    'category' => $latestFile->file_category,
                    'uploaded_at' => $latestFile->created_at?->toISOString(),
                ]
                : null,
        ];
    }

    protected function getComparisonSummary(Project $project): ?string
    {
        $config = WorkflowComparisonConfig::where('project_id', $project->id)
            ->whereNull('task_id')
            ->whereNull('subtask_id')
            ->first();

        if (! $config) {
            return null;
        }

        $latestResult = WorkflowComparisonResult::where('comparison_config_id', $config->id)
            ->latest('created_at')
            ->first();

        return $latestResult?->summary;
    }
    public function mine(Request $request): Response
    {
        abort_unless($request->user()->hasRole('Coordinator'), 403);

        return Inertia::render('Projects/Mine', [
            'pageTitle' => 'My Assigned Projects',
            'pageSubtitle' => 'Projects currently assigned to you for coordination.',
            'visibleActions' => ['View', 'View Tasks'],
            'projects' => Project::query()
                ->with(['department', 'creator', 'activePrimaryAssignment.coordinator'])
                ->whereHas('assignments', function ($query) use ($request): void {
                    $query->where('assignment_role', 'primary')
                        ->whereNull('revoked_at')
                        ->where('coordinator_id', $request->user()->id);
                })
                ->latest('updated_at')
                ->paginate(10),
        ]);
    }

    protected function formData(Project $project): array
    {
        return [
            'project' => $project,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => $this->statuses(),
            'allowedFileTypes' => app(WorkflowFileService::class)->acceptAttribute(),
            'maxFileSizeMb' => app(WorkflowFileService::class)->maxUploadMegabytes(),
        ];
    }

    protected function statuses(): array
    {
        return ProjectStatus::canonical();
    }

    protected function mapProjectToRepositoryStatus(string $projectStatus): string
    {
        return ProjectStatus::repositoryStatusForProjectStatus($projectStatus);
    }
    protected function storeInitialProjectFile(Request $request, Project $project, mixed $uploadedFile): void
    {
        $file = app(WorkflowFileService::class)->storeUploadedFile(
            $uploadedFile,
            $project,
            $request->user(),
            'attachment'
        );

        app(WorkflowNotificationService::class)->notifyFileUploaded($file);
    }
}
