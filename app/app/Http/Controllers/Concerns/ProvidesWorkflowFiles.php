<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Services\RequirementDeliverableService;
use App\Services\WorkflowFileService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

trait ProvidesWorkflowFiles
{
    protected function workflowFileProps(Project|Task|Subtask|RepositoryEntry $context, User $user, string $sectionLabel = 'Attachments'): array
    {
        $canUploadFile = $user->can('create', [WorkflowFile::class, $context]);

        return [
            'files' => $this->formatWorkflowFiles($this->workflowFilesFor($context), $user),
            'canUploadFile' => $canUploadFile,
            'fileUploadUrl' => $canUploadFile ? $this->workflowFileUploadRoute($context) : null,
            'allowedFileTypes' => app(WorkflowFileService::class)->acceptAttribute(),
            'maxFileSizeMb' => app(WorkflowFileService::class)->maxUploadMegabytes(),
            'fileSectionLabel' => $sectionLabel,
            'fileCategoryOptions' => $this->workflowFileCategoryOptions($context, $user),
            'defaultFileCategory' => $this->defaultWorkflowFileCategory($context, $user),
            'fileUploadHelperText' => $this->workflowFileUploadHelperText($context, $user),
        ];
    }
    protected function workflowFileCategoryOptions(Project|Task|Subtask|RepositoryEntry $context, User $user): array
    {
        $labels = [
            'requirement' => 'Requirement',
            'follow_up' => 'Follow-up',
            'deliverable' => 'Deliverable',
            'evidence' => 'Evidence',
            'attachment' => 'Attachment',
            'other' => 'Other',
            'repository_document' => 'Repository Document',
        ];

        return collect($this->workflowFileCategoryValues($context, $user))
            ->map(fn (string $value): array => ['value' => $value, 'label' => $labels[$value] ?? ucfirst(str_replace('_', ' ', $value))])
            ->values()
            ->all();
    }

    protected function workflowFileCategoryValues(Project|Task|Subtask|RepositoryEntry $context, User $user): array
    {
        if ($context instanceof Project) {
            if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
                return ['requirement', 'attachment', 'other'];
            }

            if ($user->hasRole('Coordinator')) {
                return ['follow_up', 'deliverable', 'evidence', 'attachment', 'other'];
            }
        }

        if ($context instanceof Subtask) {
            if ($user->hasRole('Subordinate')) {
                return ['evidence', 'attachment', 'other'];
            }

            return ['follow_up', 'deliverable', 'evidence', 'attachment', 'other'];
        }

        if ($context instanceof RepositoryEntry) {
            return ['repository_document', 'attachment', 'other'];
        }

        return ['requirement', 'follow_up', 'deliverable', 'evidence', 'attachment', 'other'];
    }

    protected function defaultWorkflowFileCategory(Project|Task|Subtask|RepositoryEntry $context, User $user): string
    {
        if ($context instanceof Project) {
            return $user->hasAnyRole(['Admin', 'PM/Manager']) ? 'requirement' : 'follow_up';
        }

        if ($context instanceof Subtask && $user->hasRole('Subordinate')) {
            return 'evidence';
        }

        return $this->workflowFileCategoryValues($context, $user)[0] ?? 'attachment';
    }

    protected function workflowFileUploadHelperText(Project|Task|Subtask|RepositoryEntry $context, User $user): string
    {
        if ($context instanceof Project && $user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return 'Upload the project requirement or instruction file. Coordinator follow-up/evidence files will be compared against this requirement.';
        }

        if ($context instanceof Project && $user->hasRole('Coordinator')) {
            return 'Upload follow-up, deliverable, or evidence files after completing assigned work. PM/Admin will use these for AI comparison.';
        }

        return 'AI comparison requires at least one Requirement file and one Deliverable/Evidence file.';
    }

    protected function workflowFilesFor(Project|Task|Subtask|RepositoryEntry $context): EloquentCollection
    {
        return $context->files()
            ->with('uploader')
            ->latest()
            ->limit(100)
            ->get();
    }

    protected function formatWorkflowFiles(Collection|EloquentCollection $files, User $user): array
    {
        return $files->map(fn (WorkflowFile $file): array => [
            'id' => $file->id,
            'original_name' => $file->original_name,
            'size_human' => $this->humanFileSize($file->size),
            'mime_type' => $file->mime_type,
            'file_category' => $file->file_category,
            'uploaded_by_name' => $file->uploader?->name ?? 'Unknown',
            'uploaded_at' => $file->created_at?->toDateTimeString(),
            'download_url' => route('workflow-files.download', $file),
            'can_delete' => $user->can('delete', $file),
            'delete_url' => $user->can('delete', $file) ? route('workflow-files.destroy', $file) : null,
        ])->all();
    }

    protected function workflowFileUploadRoute(Project|Task|Subtask|RepositoryEntry $context): string
    {
        return match (true) {
            $context instanceof Project => route('projects.files.store', $context),
            $context instanceof Task => route('tasks.files.store', $context),
            $context instanceof Subtask => route('subtasks.files.store', $context),
            $context instanceof RepositoryEntry => route('repository.files.store', $context),
        };
    }

    protected function humanFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    protected function comparisonProps(Project|Task|Subtask $context, User $user): array
    {
        $service = app(RequirementDeliverableService::class);
        $isConfigured = $service->isAiConfigured();

        $runUrl = match (true) {
            $context instanceof Project => route('projects.comparison.run', $context),
            $context instanceof Task => route('tasks.comparison.run', $context),
            $context instanceof Subtask => route('subtasks.comparison.run', $context),
        };

        $clearUrl = match (true) {
            $context instanceof Project => route('projects.comparison.clear', $context),
            $context instanceof Task => route('tasks.comparison.clear', $context),
            $context instanceof Subtask => route('subtasks.comparison.clear', $context),
        };

        // Only show comparison to users who can view the context
        $canView = $user->can('view', $context) ||
            ($context instanceof Subtask && $user->can('viewAssigned', $context));

        if (! $canView) {
            return ['comparisonResult' => null, 'isComparisonConfigured' => false, 'comparisonRunUrl' => null, 'comparisonClearUrl' => null];
        }

        $result = $service->getComparisonResult($context);

        return [
            'comparisonResult' => $result,
            'isComparisonConfigured' => $isConfigured,
            'comparisonRunUrl' => $runUrl,
            'comparisonClearUrl' => $clearUrl,
        ];
    }
}



