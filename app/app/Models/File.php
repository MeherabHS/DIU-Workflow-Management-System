<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class File extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'task_id',
        'subtask_id',
        'repository_entry_id',
        'message_id',
        'uploaded_by',
        'original_name',
        'stored_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'category',
        'is_final',
        'is_archived',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_final' => 'boolean',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}