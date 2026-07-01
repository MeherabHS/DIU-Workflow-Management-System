<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowRequirement extends Model
{
    protected $fillable = [
        'comparison_config_id',
        'workflow_file_id',
        'requirement_text',
        'source_page',
    ];

    public function comparisonConfig(): BelongsTo
    {
        return $this->belongsTo(WorkflowComparisonConfig::class);
    }

    public function workflowFile(): BelongsTo
    {
        return $this->belongsTo(WorkflowFile::class);
    }
}
