<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowComparisonConfig extends Model
{
    protected $fillable = [
        'project_id',
        'task_id',
        'subtask_id',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
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

    public function requirements(): HasMany
    {
        return $this->hasMany(WorkflowRequirement::class);
    }

    public function deliverables(): HasMany
    {
        return $this->hasMany(WorkflowDeliverable::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(WorkflowComparisonResult::class);
    }
}
