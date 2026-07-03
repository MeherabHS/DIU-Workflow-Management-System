<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Http\Requests\StoreRepositoryEntryRequest;
use App\Http\Requests\StoreRepositoryUpdateRequest;
use App\Http\Requests\UpdateRepositoryEntryRequest;
use App\Models\Department;
use App\Models\RepositoryEntry;
use App\Models\RepositoryUpdate;
use App\Models\User;
use App\Support\ProjectStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RepositoryController extends Controller
{
    use ProvidesWorkflowFiles;

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->can('view repository'), 403);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(ProjectStatus::repositoryStatuses())],
            'type' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();
        $query = RepositoryEntry::query()
            ->with(['department', 'responsibleUser', 'creator', 'project'])
            ->latest('updated_at');

        if ($user->hasRole('Coordinator') && ! $user->hasAnyRole(['Admin', 'PM/Manager'])) {
            $assignedProjectIds = $user->activeAssignedProjects()->pluck('projects.id');
            $query->whereIn('project_id', $assignedProjectIds);
        }

        if ($search = $request->string('search')->toString()) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('client_or_office', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($q) use ($search): void {
                        $q->where('title', 'like', "%{$search}%");
                    })
                    ->orWhereHas('department', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('responsibleUser', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('creator', function ($q) use ($search): void {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($type = $request->string('type')->toString()) {
            if ($type === 'not_set') {
                $query->where(function ($q): void {
                    $q->whereNull('type')->orWhere('type', '');
                });
            } else {
                $query->where('type', $type);
            }
        }

        if ($departmentId = $request->integer('department_id')) {
            $query->where('department_id', $departmentId);
        }

        if ($deadlineScope = $request->string('deadline_scope')->toString()) {
            if ($deadlineScope === 'upcoming') {
                $query->whereDate('deadline', '>=', today())->orderBy('deadline');
            }

            if ($deadlineScope === 'overdue') {
                $query->whereDate('deadline', '<', today())->orderBy('deadline');
            }
        }

        return Inertia::render('Repository/Index', [
            'pageTitle' => 'Repository Tracker',
            'pageSubtitle' => 'Permanent institutional repository records and timeline tracking.',
            'primaryAction' => $request->user()->can('create repository entry') ? 'Create Repository Entry' : null,
            'entries' => $query->paginate(10)->withQueryString(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'types' => array_merge(
                ['not_set'],
                RepositoryEntry::query()->whereNotNull('type')->where('type', '!=', '')->distinct()->orderBy('type')->pluck('type')->toArray()
            ),
            'statuses' => $this->statuses(),
            'filters' => $request->only(['search', 'status', 'type', 'department_id', 'deadline_scope']),
        ]);
    }

    public function create(): Response
    {
        abort_unless(auth()->user()?->can('create repository entry'), 403);

        return Inertia::render('Repository/Form', $this->formData(new RepositoryEntry([
            'status' => 'planned',
            'value_currency' => 'BDT',
        ])) + [
            'pageTitle' => 'Create Repository Entry',
            'submitLabel' => 'Create Repository Entry',
            'method' => 'post',
            'action' => route('repository.store'),
        ]);
    }

    public function store(StoreRepositoryEntryRequest $request): RedirectResponse
    {
        $entry = RepositoryEntry::create([
            ...$request->validated(),
            'value_currency' => $request->validated('value_currency') ?: 'BDT',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('repository.show', $entry)->with('status', 'Repository entry created successfully.');
    }

    public function show(Request $request, RepositoryEntry $repositoryEntry): Response
    {
        abort_unless($this->canViewRepositoryEntry($request->user(), $repositoryEntry), 403);

        $repositoryEntry->load(['department', 'responsibleUser', 'creator', 'project', 'updates.user', 'finalizedBy']);

        return Inertia::render('Repository/Show', [
            'pageTitle' => 'Repository Details',
            'entry' => [
                ...$repositoryEntry->toArray(),
                'finalized_by_name' => $repositoryEntry->finalizedBy?->name,
                'final_status_snapshot' => $repositoryEntry->final_status_snapshot,
                'project' => $repositoryEntry->project
                    ? [
                        'id' => $repositoryEntry->project->id,
                        'title' => $repositoryEntry->project->title,
                        'status' => $repositoryEntry->project->status,
                        'priority' => $repositoryEntry->project->priority,
                        'deadline' => $repositoryEntry->project->deadline?->format('Y-m-d'),
                        'completed_at' => $repositoryEntry->project->completed_at?->toISOString(),
                    ]
                    : null,
            ],
            'statuses' => $this->statuses(),
            ...$this->workflowFileProps($repositoryEntry, $request->user(), 'Attachments'),
        ]);
    }

    public function edit(RepositoryEntry $repositoryEntry): Response
    {
        $user = auth()->user();
        abort_unless($this->canUpdateRepositoryEntry($user, $repositoryEntry), 403);

        return Inertia::render('Repository/Form', $this->formData($repositoryEntry) + [
            'pageTitle' => 'Edit Repository Entry',
            'submitLabel' => 'Update Repository Entry',
            'method' => 'patch',
            'action' => route('repository.update', $repositoryEntry),
        ]);
    }

    public function update(UpdateRepositoryEntryRequest $request, RepositoryEntry $repositoryEntry): RedirectResponse
    {
        abort_unless($this->canUpdateRepositoryEntry($request->user(), $repositoryEntry), 403);

        $repositoryEntry->update([
            ...$request->validated(),
            'value_currency' => $request->validated('value_currency') ?: 'BDT',
        ]);

        return redirect()->route('repository.show', $repositoryEntry)->with('status', 'Repository entry updated successfully.');
    }

    public function storeUpdate(StoreRepositoryUpdateRequest $request, RepositoryEntry $repositoryEntry): RedirectResponse
    {
        abort_unless($this->canAddRepositoryUpdate($request->user(), $repositoryEntry), 403);

        DB::transaction(function () use ($request, $repositoryEntry): void {
            $currentStatus = $repositoryEntry->status;
            $newStatus = $request->validated('new_status');

            RepositoryUpdate::create([
                'repository_entry_id' => $repositoryEntry->id,
                'user_id' => $request->user()->id,
                'update_type' => $request->validated('update_type'),
                'old_status' => $newStatus ? $currentStatus : null,
                'new_status' => $newStatus,
                'note' => $request->validated('note'),
            ]);

            if ($newStatus) {
                $updates = ['status' => $newStatus];

                if ($newStatus === 'submitted' && $repositoryEntry->submitted_at === null) {
                    $updates['submitted_at'] = now();
                }

                if ($newStatus === 'completed' && $repositoryEntry->completed_at === null) {
                    $updates['completed_at'] = now();
                }

                if ($newStatus === 'archived' && $repositoryEntry->archived_at === null) {
                    $updates['archived_at'] = now();
                }

                $repositoryEntry->update($updates);
            }
        });

        return redirect()->route('repository.show', $repositoryEntry)->with('status', 'Repository timeline update added successfully.');
    }

    protected function formData(RepositoryEntry $repositoryEntry): array
    {
        return [
            'entry' => $repositoryEntry,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'responsibleUsers' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => $this->statuses(),
        ];
    }

    protected function statuses(): array
    {
        return array_merge(
            [['value' => '', 'label' => 'All Status']],
            array_map(
                fn ($status) => ['value' => $status, 'label' => ProjectStatus::label($status)],
                ProjectStatus::repositoryStatuses()
            )
        );
    }

    private function canViewRepositoryEntry(?User $user, RepositoryEntry $entry): bool
    {
        if (! $user || ! $user->can('view repository')) {
            return false;
        }

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return true;
        }

        if (! $user->hasRole('Coordinator') || $entry->project_id === null) {
            return false;
        }

        return $entry->project?->assignments()
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->where('coordinator_id', $user->id)
            ->exists() ?? false;
    }

    private function canUpdateRepositoryEntry(?User $user, RepositoryEntry $entry): bool
    {
        return $user !== null
            && $user->can('update repository entry')
            && $this->canViewRepositoryEntry($user, $entry);
    }

    private function canAddRepositoryUpdate(?User $user, RepositoryEntry $entry): bool
    {
        return $user !== null
            && $user->can('add repository update')
            && $this->canViewRepositoryEntry($user, $entry);
    }
}