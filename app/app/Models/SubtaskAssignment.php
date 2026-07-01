<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubtaskAssignment extends Model
{
    protected $fillable = [
        'subtask_id',
        'subordinate_id',
        'assigned_by',
        'assigned_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function subtask(): BelongsTo
    {
        return $this->belongsTo(Subtask::class);
    }

    public function subordinate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subordinate_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}