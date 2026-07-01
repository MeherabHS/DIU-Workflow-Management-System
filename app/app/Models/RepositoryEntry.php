<?php

namespace App\Models;

use Database\Factories\RepositoryEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RepositoryEntry extends Model
{
    /** @use HasFactory<RepositoryEntryFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'project_id',
        'title',
        'type',
        'department_id',
        'client_or_office',
        'responsible_user_id',
        'status',
        'deadline',
        'value_amount',
        'value_currency',
        'description',
        'final_summary',
        'submitted_at',
        'completed_at',
        'archived_at',
        'created_by',
        'finalized_at',
        'finalized_by',
        'final_status_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
            'value_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'finalized_at' => 'datetime',
            'final_status_snapshot' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(RepositoryUpdate::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(WorkflowFile::class);
    }
}
