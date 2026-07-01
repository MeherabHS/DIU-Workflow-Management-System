<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use App\Services\RequirementDeliverableService;
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
            'allowedFileTypes' => '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.txt,.csv,.zip',
            'maxFileSizeMb' => 10,
            'fileSectionLabel' => $sectionLabel,
        ];
    }

    protected function workflowFilesFor(Project|Task|Subtask|RepositoryEntry $context): EloquentCollection
    {
        return $context->files()
            ->with('uploader')
            ->latest()
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
