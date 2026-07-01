<?php

namespace App\Models;

use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subtask extends Model
{
    /** @use HasFactory<SubtaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'task_id',
        'title',
        'description',
        'created_by',
        'status',
        'priority',
        'deadline',
        'progress_note',
        'submitted_at',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(SubtaskAssignment::class);
    }

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'subtask_assignments', 'subtask_id', 'subordinate_id')
            ->withPivot(['assigned_by', 'assigned_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkflowFile::class);
    }

    public function scopeWithActiveAssignmentFor(Builder $query, User $user): Builder
    {
        return $query
            ->select('subtasks.*')
            ->selectSub(
                SubtaskAssignment::query()
                    ->select('assigned_at')
                    ->whereColumn('subtask_id', 'subtasks.id')
                    ->where('subordinate_id', $user->id)
                    ->whereNull('revoked_at')
                    ->latest('assigned_at')
                    ->limit(1),
                'current_assigned_at'
            )
            ->withCasts(['current_assigned_at' => 'datetime']);
    }

    public function loadActiveAssignmentFor(User $user): static
    {
        $assignment = $this->assignments()
            ->where('subordinate_id', $user->id)
            ->whereNull('revoked_at')
            ->latest('assigned_at')
            ->first();

        $this->setAttribute('current_assigned_at', $assignment?->assigned_at);

        return $this;
    }
}


