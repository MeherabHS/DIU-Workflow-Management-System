<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowNotification extends Model
{
    protected $fillable = [
        'user_id',
        'actor_id',
        'project_id',
        'task_id',
        'subtask_id',
        'workflow_message_id',
        'workflow_file_id',
        'type',
        'title',
        'body',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
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

    public function workflowMessage(): BelongsTo
    {
        return $this->belongsTo(WorkflowMessage::class);
    }

    public function workflowFile(): BelongsTo
    {
        return $this->belongsTo(WorkflowFile::class);
    }
}
