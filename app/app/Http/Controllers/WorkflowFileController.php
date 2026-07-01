<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ProvidesWorkflowFiles;
use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\WorkflowFile;
use App\Services\WorkflowNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkflowFileController extends Controller
{
    use ProvidesWorkflowFiles;

    public function projectIndex(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        return response()->json($this->formatWorkflowFiles($this->workflowFilesFor($project), $request->user()));
    }

    public function projectStore(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('create', [WorkflowFile::class, $project]);

        return $this->storeForContext($request, $project, ['project_id' => $project->id], 'attachment');
    }

    public function taskIndex(Request $request, Task $task)
    {
        $this->authorize('view', $task);

        return response()->json($this->formatWorkflowFiles($this->workflowFilesFor($task), $request->user()));
    }

    public function taskStore(Request $request, Task $task): RedirectResponse
    {
        $this->authorize('create', [WorkflowFile::class, $task]);

        return $this->storeForContext($request, $task, [
            'project_id' => $task->project_id,
            'task_id' => $task->id,
        ], 'attachment');
    }

    public function subtaskIndex(Request $request, Subtask $subtask)
    {
        $this->authorize('view', $this->workflowFileForSubtask($subtask));

        return response()->json($this->formatWorkflowFiles($this->workflowFilesFor($subtask), $request->user()));
    }

    public function subtaskStore(Request $request, Subtask $subtask): RedirectResponse
    {
        $this->authorize('create', [WorkflowFile::class, $subtask]);

        $defaultCategory = $request->user()->hasRole('Subordinate') ? 'evidence' : 'attachment';

        return $this->storeForContext($request, $subtask, [
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
        ], $defaultCategory);
    }

    public function repositoryIndex(Request $request, RepositoryEntry $repositoryEntry)
    {
        $this->authorize('view', $this->workflowFileForRepositoryEntry($repositoryEntry));

        return response()->json($this->formatWorkflowFiles($this->workflowFilesFor($repositoryEntry), $request->user()));
    }

    public function repositoryStore(Request $request, RepositoryEntry $repositoryEntry): RedirectResponse
    {
        $this->authorize('create', [WorkflowFile::class, $repositoryEntry]);

        return $this->storeForContext($request, $repositoryEntry, [
            'project_id' => $repositoryEntry->project_id,
            'repository_entry_id' => $repositoryEntry->id,
        ], 'repository_document');
    }

    public function download(WorkflowFile $workflowFile): StreamedResponse
    {
        $this->authorize('download', $workflowFile);

        abort_unless(Storage::disk($workflowFile->disk)->exists($workflowFile->path), 404);

        return Storage::disk($workflowFile->disk)->download($workflowFile->path, $workflowFile->original_name);
    }

    public function destroy(WorkflowFile $workflowFile): RedirectResponse
    {
        $this->authorize('delete', $workflowFile);

        // Delete physical file from storage before deleting DB record
        Storage::disk($workflowFile->disk)->delete($workflowFile->path);

        $workflowFile->delete();

        return redirect()->back()->with('status', 'File deleted successfully.');
    }

    protected function storeForContext(Request $request, Project|Task|Subtask|RepositoryEntry $context, array $contextColumns, string $defaultCategory): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,txt,csv,zip'],
            'file_category' => ['nullable', 'string', Rule::in(['attachment', 'evidence', 'reference', 'repository_document', 'feedback_attachment', 'requirement'])],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless(collect($contextColumns)->filter()->isNotEmpty(), 422);

        $uploadedFile = $validated['file'];
        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? '.'.$extension : '');
        $directory = 'workflow-files/'.now()->format('Y/m');
        $path = $uploadedFile->storeAs($directory, $storedName, 'local');

        $file = WorkflowFile::create([
            ...$contextColumns,
            'uploaded_by' => $request->user()->id,
            'original_name' => $uploadedFile->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $uploadedFile->getMimeType(),
            'size' => $uploadedFile->getSize(),
            'file_category' => $validated['file_category'] ?? $defaultCategory,
            'description' => $validated['description'] ?? null,
        ]);

        app(WorkflowNotificationService::class)->notifyFileUploaded($file);

        return redirect()->back()->with('status', 'File uploaded successfully.');
    }

    protected function workflowFileForSubtask(Subtask $subtask): WorkflowFile
    {
        return new WorkflowFile([
            'project_id' => $subtask->project_id,
            'task_id' => $subtask->task_id,
            'subtask_id' => $subtask->id,
        ]);
    }

    protected function workflowFileForRepositoryEntry(RepositoryEntry $repositoryEntry): WorkflowFile
    {
        return new WorkflowFile([
            'project_id' => $repositoryEntry->project_id,
            'repository_entry_id' => $repositoryEntry->id,
        ]);
    }
}
