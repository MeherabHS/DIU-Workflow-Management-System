<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveRecord extends Model
{
    protected $fillable = [
        'project_id',
        'record_type',
        'record_id',
        'archived_by',
        'archive_reason',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}