<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RepositoryUpdate extends Model
{
    protected $fillable = [
        'repository_entry_id',
        'user_id',
        'update_type',
        'old_status',
        'new_status',
        'note',
    ];

    public function repositoryEntry(): BelongsTo
    {
        return $this->belongsTo(RepositoryEntry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}