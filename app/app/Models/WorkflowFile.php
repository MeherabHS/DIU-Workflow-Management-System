<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowFile extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'task_id',
        'subtask_id',
        'repository_entry_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'file_category',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function repositoryEntry(): BelongsTo
    {
        return $this->belongsTo(RepositoryEntry::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
