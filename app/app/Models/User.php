<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'department_id', 'designation', 'phone', 'is_active', 'profile_photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'created_by');
    }

    public function coordinatedProjectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class, 'coordinator_id');
    }

    public function projectAssignmentsCreated(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class, 'assigned_by');
    }

    public function assignedProjects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_assignments', 'coordinator_id', 'project_id')
            ->withPivot(['assigned_by', 'assignment_role', 'assigned_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function activeAssignedProjects(): BelongsToMany
    {
        return $this->assignedProjects()
            ->wherePivot('assignment_role', 'primary')
            ->wherePivotNull('revoked_at');
    }

    public function assignedSubtasks(): BelongsToMany
    {
        return $this->belongsToMany(Subtask::class, 'subtask_assignments', 'subordinate_id', 'subtask_id')
            ->withPivot(['assigned_by', 'assigned_at', 'revoked_at'])
            ->withTimestamps();
    }

    public function activeAssignedSubtasks(): BelongsToMany
    {
        return $this->assignedSubtasks()->wherePivotNull('revoked_at');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    public function workflowMessages(): HasMany
    {
        return $this->hasMany(WorkflowMessage::class, 'sender_id');
    }

    public function uploadedWorkflowFiles(): HasMany
    {
        return $this->hasMany(WorkflowFile::class, 'uploaded_by');
    }

    public function workflowNotifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class);
    }

    public function unreadWorkflowNotifications(): HasMany
    {
        return $this->hasMany(WorkflowNotification::class)->whereNull('read_at');
    }

    /**
     * Get the URL for the user's profile photo.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo_path) {
            return '/storage/'.ltrim($this->profile_photo_path, '/');
        }
        return '';
    }

    /**
     * Get the initials for the user's avatar placeholder.
     */
    public function getInitialsAttribute(): string
    {
        $parts = explode(' ', trim($this->name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($this->name, 0, 2));
    }

    /**
     * Get the user's role names as a simple string array for frontend display.
     */
    public function getRoleNamesAttribute(): array
    {
        return $this->getRoleNames()->values()->all();
    }
}





