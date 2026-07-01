<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowComparisonResult extends Model
{
    protected $fillable = [
        'comparison_config_id',
        'requirement_id',
        'deliverable_id',
        'status',
        'matched_items',
        'completion_percentage',
        'summary',
        'raw_ai_response',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'matched_items' => 'array',
            'completion_percentage' => 'decimal:2',
        ];
    }

    public function comparisonConfig(): BelongsTo
    {
        return $this->belongsTo(WorkflowComparisonConfig::class);
    }

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(WorkflowRequirement::class);
    }

    public function deliverable(): BelongsTo
    {
        return $this->belongsTo(WorkflowDeliverable::class);
    }
}
