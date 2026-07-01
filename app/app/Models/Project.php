<?php

namespace App\Models;

use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'department_id',
        'created_by',
        'status',
        'priority',
        'start_date',
        'deadline',
        'completed_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function activePrimaryAssignment(): HasOne
    {
        return $this->hasOne(ProjectAssignment::class)
            ->where('assignment_role', 'primary')
            ->whereNull('revoked_at')
            ->latestOfMany('assigned_at');
    }

    public function coordinators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_assignments', 'project_id', 'coordinator_id')
            ->withPivot(['assigned_by', 'assignment_role', 'assigned_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class);
    }

    public function repositoryEntries(): HasMany
    {
        return $this->hasMany(RepositoryEntry::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkflowFile::class);
    }

    public function archiveRecords(): HasMany
    {
        return $this->hasMany(ArchiveRecord::class);
    }
}


