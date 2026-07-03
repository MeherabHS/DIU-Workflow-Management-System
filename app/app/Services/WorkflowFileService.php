<?php

namespace App\Services;

use App\Models\Project;
use App\Models\RepositoryEntry;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkflowFileService
{
    public function allowedExtensions(): array
    {
        return [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'zip',
            'png',
            'jpg',
            'jpeg',
            'webp',
            'txt',
            'csv',
        ];
    }

    public function maxUploadKilobytes(): int
    {
        return 102400;
    }

    public function maxUploadMegabytes(): int
    {
        return 100;
    }

    public function acceptAttribute(): string
    {
        return collect($this->allowedExtensions())
            ->map(fn (string $extension): string => '.'.$extension)
            ->implode(',');
    }

    public function validationRules(bool $required = true): array
    {
        $allowedExtensions = $this->allowedExtensions();

        return [
            'file' => [
                $required ? 'required' : 'nullable',
                'file',
                'max:'.$this->maxUploadKilobytes(),
                'mimes:'.implode(',', $allowedExtensions),
                function (string $attribute, mixed $value, \Closure $fail) use ($allowedExtensions): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());

                    if (! in_array($extension, $allowedExtensions, true)) {
                        $fail('The '.$attribute.' must be a file of type: '.implode(', ', $allowedExtensions).'.');
                    }
                },
            ],
        ];
    }

    public function storeUploadedFile(
        UploadedFile $file,
        Model $context,
        User $uploader,
        string $category = 'attachment',
        ?string $description = null
    ): WorkflowFile {
        $extension = strtolower($file->getClientOriginalExtension());
        $storedName = Str::uuid().($extension ? '.'.$extension : '');
        $directory = $this->pathForContext($context);
        $path = $file->storeAs($directory, $storedName, 'local');

        return WorkflowFile::create([
            ...$this->contextColumns($context),
            'uploaded_by' => $uploader->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'file_category' => $category,
            'description' => $description,
        ]);
    }

    public function deleteStoredFile(WorkflowFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    public function pathForContext(Model $context): string
    {
        return 'workflow-files/'.now()->format('Y/m');
    }

    protected function contextColumns(Model $context): array
    {
        return match (true) {
            $context instanceof Project => ['project_id' => $context->id],
            $context instanceof Task => ['project_id' => $context->project_id, 'task_id' => $context->id],
            $context instanceof Subtask => ['project_id' => $context->project_id, 'task_id' => $context->task_id, 'subtask_id' => $context->id],
            $context instanceof RepositoryEntry => ['project_id' => $context->project_id, 'repository_entry_id' => $context->id],
            default => throw new \InvalidArgumentException('Unsupported workflow file context.'),
        };
    }
}



