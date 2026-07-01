<?php

namespace App\Models;

use Database\Factories\WorkflowMessageFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class WorkflowMessage extends Model
{
    /** @use HasFactory<WorkflowMessageFactory> */
    use HasFactory, SoftDeletes;

    public const TYPES = ['message', 'feedback', 'follow_up', 'progress_note', 'clarification'];

    protected $fillable = [
        'project_id',
        'task_id',
        'subtask_id',
        'sender_id',
        'message_type',
        'body',
        'visibility',
    ];

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->tryDecryptBody($value),
            set: fn ($value) => $value !== null ? Crypt::encryptString($value) : null,
        );
    }

    protected function tryDecryptBody(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Exception) {
            return $value;
        }
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

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
