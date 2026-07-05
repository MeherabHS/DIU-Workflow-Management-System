<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\WorkflowComparisonConfig;
use App\Models\WorkflowComparisonResult;
use App\Models\WorkflowDeliverable;
use App\Models\WorkflowFile;
use App\Models\WorkflowRequirement;

class RequirementDeliverableService
{
    public function __construct(
        protected FileTextExtractionService $extractionService,
        protected AiComparisonService $aiService,
    ) {}

    /**
     * Run the full comparison pipeline for a context.
     * Returns the comparison result array.
     */
    public function processComparison(Project|Task|Subtask $context): array
    {
        if (! $this->aiService->isConfigured()) {
            return $this->storeConfigMissingResult($context);
        }

        $config = $this->getOrCreateConfig($context);
        $requirementFiles = $this->getRequirementFiles($context);
        $deliverableFiles = $this->getDeliverableFiles($context);

        if (empty($requirementFiles)) {
            return [
                'isConfigured' => true,
                'status' => 'no_requirements',
                'summary' => 'No requirement file has been uploaded yet. Upload a file with category Requirement.',
                'completion_percentage' => 0,
                ...$this->emptyStructuredFields(),
            ];
        }

        if (empty($deliverableFiles)) {
            return [
                'isConfigured' => true,
                'status' => 'no_deliverables',
                'summary' => 'Requirement file found, but no deliverable or evidence file has been uploaded yet.',
                'completion_percentage' => 0,
                ...$this->emptyStructuredFields(),
            ];
        }

        $requirements = [];
        $extractionErrors = [];

        foreach ($requirementFiles as $file) {
            $result = $this->extractionService->extractText($file);
            if ($result['error']) {
                $extractionErrors[] = "{$file->original_name}: {$result['error']}";
                continue;
            }
            if (trim($result['text']) === '') {
                $extractionErrors[] = "{$file->original_name}: No readable text found. Scanned PDFs are not supported yet.";
                continue;
            }

            $extracted = $this->aiService->extractRequirements($result['text']);
            if (isset($extracted['error'])) {
                $extractionErrors[] = "{$file->original_name}: AI extraction failed - {$extracted['error']}";
                continue;
            }

            foreach ($extracted as $req) {
                if (isset($req['text'])) {
                    $requirements[] = WorkflowRequirement::create([
                        'comparison_config_id' => $config->id,
                        'workflow_file_id' => $file->id,
                        'requirement_text' => $req['text'],
                        'source_page' => $req['page'] ?? null,
                    ]);
                }
            }
        }

        if (empty($requirements)) {
            return [
                'isConfigured' => true,
                'status' => 'extraction_failed',
                'summary' => 'Could not extract requirements from uploaded files.',
                'errors' => $extractionErrors,
                'completion_percentage' => 0,
                ...$this->emptyStructuredFields(),
            ];
        }

        $deliverables = [];

        foreach ($deliverableFiles as $file) {
            $result = $this->extractionService->extractText($file);
            if ($result['error']) {
                $extractionErrors[] = "{$file->original_name}: {$result['error']}";
                continue;
            }
            if (trim($result['text']) === '') {
                continue;
            }

            $extracted = $this->aiService->extractDeliverables($result['text']);
            if (isset($extracted['error'])) {
                $extractionErrors[] = "{$file->original_name}: AI extraction failed - {$extracted['error']}";
                continue;
            }

            foreach ($extracted as $del) {
                if (isset($del['text'])) {
                    $deliverables[] = WorkflowDeliverable::create([
                        'comparison_config_id' => $config->id,
                        'workflow_file_id' => $file->id,
                        'deliverable_text' => $del['text'],
                        'source_page' => $del['page'] ?? null,
                    ]);
                }
            }
        }

        $reqData = collect($requirements)->map(fn ($r) => ['text' => $r->requirement_text, 'page' => $r->source_page])->all();
        $delData = collect($deliverables)->map(fn ($d) => ['text' => $d->deliverable_text, 'page' => $d->source_page])->all();

        $comparisonResult = $this->aiService->compareRequirements($reqData, $delData);

        if (isset($comparisonResult['error'])) {
            return $this->storeFailedResult($config, $comparisonResult);
        }

        return $this->storeSuccessResult($config, $comparisonResult, $extractionErrors);
    }

    /**
     * Get the latest comparison result for a context.
     */
    public function getComparisonResult(Project|Task|Subtask $context): ?array
    {
        $config = $this->findConfig($context);
        if (! $config) {
            return null;
        }

        $latestResult = $config->results()->latest()->first();
        if (! $latestResult) {
            return null;
        }

        return [
            'status' => $latestResult->status,
            'completion_percentage' => (float) $latestResult->completion_percentage,
            'summary' => $latestResult->summary,
            'items' => $latestResult->matched_items ?? [],
            ...$this->structuredFieldsFromResult($latestResult),
            'error_message' => $latestResult->error_message,
            'created_at' => $latestResult->created_at?->diffForHumans(),
        ];
    }

    /**
     * Check if AI is configured.
     */
    public function isAiConfigured(): bool
    {
        return $this->aiService->isConfigured();
    }

    protected function getOrCreateConfig(Project|Task|Subtask $context): WorkflowComparisonConfig
    {
        $config = $this->findConfig($context);
        if ($config) {
            return $config;
        }

        return WorkflowComparisonConfig::create([
            'project_id' => $context instanceof Project ? $context->id : ($context instanceof Task ? $context->project_id : $context->project_id),
            'task_id' => $context instanceof Task ? $context->id : ($context instanceof Subtask ? $context->task_id : null),
            'subtask_id' => $context instanceof Subtask ? $context->id : null,
            'enabled' => true,
        ]);
    }

    protected function findConfig(Project|Task|Subtask $context): ?WorkflowComparisonConfig
    {
        if ($context instanceof Subtask) {
            return WorkflowComparisonConfig::where('subtask_id', $context->id)->first();
        }
        if ($context instanceof Task) {
            return WorkflowComparisonConfig::where('task_id', $context->id)->first();
        }

        return WorkflowComparisonConfig::where('project_id', $context->id)
            ->whereNull('task_id')
            ->whereNull('subtask_id')
            ->first();
    }

    protected function getRequirementFiles(Project|Task|Subtask $context): array
    {
        if ($context instanceof Subtask) {
            return WorkflowFile::where('subtask_id', $context->id)
                ->where('file_category', 'requirement')
                ->orderBy('created_at')
                ->get()
                ->all();
        }
        if ($context instanceof Task) {
            return WorkflowFile::where('task_id', $context->id)
                ->where('file_category', 'requirement')
                ->orderBy('created_at')
                ->get()
                ->all();
        }

        return WorkflowFile::where('project_id', $context->id)
            ->whereNull('task_id')
            ->whereNull('subtask_id')
            ->where('file_category', 'requirement')
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    protected function getDeliverableFiles(Project|Task|Subtask $context): array
    {
        $categories = ['deliverable', 'evidence', 'attachment'];

        if ($context instanceof Subtask) {
            return WorkflowFile::where('subtask_id', $context->id)
                ->whereIn('file_category', $categories)
                ->orderBy('created_at')
                ->get()
                ->all();
        }
        if ($context instanceof Task) {
            return WorkflowFile::where('task_id', $context->id)
                ->whereIn('file_category', $categories)
                ->orderBy('created_at')
                ->get()
                ->all();
        }

        return WorkflowFile::where('project_id', $context->id)
            ->whereNull('task_id')
            ->whereNull('subtask_id')
            ->whereIn('file_category', $categories)
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    protected function storeConfigMissingResult(Project|Task|Subtask $context): array
    {
        $config = $this->getOrCreateConfig($context);

        WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => 'config_missing',
            'completion_percentage' => 0,
            'summary' => 'AI comparison not configured. Add AI credentials to .env to enable.',
        ]);

        return [
            'isConfigured' => false,
            'status' => 'config_missing',
            'summary' => 'AI comparison not configured. Add AI credentials to .env to enable.',
            'completion_percentage' => 0,
            ...$this->emptyStructuredFields(),
        ];
    }

    protected function storeFailedResult(WorkflowComparisonConfig $config, array $aiResponse): array
    {
        WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => 'failed',
            'completion_percentage' => 0,
            'summary' => 'Comparison failed.',
            'raw_ai_response' => $aiResponse['raw'] ?? null,
            'error_message' => $aiResponse['error'] ?? 'Unknown error',
        ]);

        return [
            'isConfigured' => true,
            'status' => 'failed',
            'summary' => 'Comparison failed: ' . ($aiResponse['error'] ?? 'Unknown error'),
            'completion_percentage' => 0,
            ...$this->emptyStructuredFields(),
            'error_message' => $aiResponse['error'] ?? 'Unknown error',
        ];
    }

    protected function storeSuccessResult(WorkflowComparisonConfig $config, array $aiResponse, array $extractionErrors = []): array
    {
        $items = $aiResponse['items'] ?? [];
        $completionPercentage = $aiResponse['completion_percentage'] ?? 0;
        $summary = $aiResponse['summary'] ?? '';

        $statuses = collect($items)->pluck('status')->filter();
        $overallStatus = $aiResponse['status'] ?? 'partially_completed';
        if ($statuses->isNotEmpty()) {
            if ($statuses->every(fn ($s) => $s === 'completed')) {
                $overallStatus = 'completed';
            } elseif ($statuses->every(fn ($s) => in_array($s, ['missing', 'unclear'], true))) {
                $overallStatus = 'missing';
            }
        }

        $result = WorkflowComparisonResult::create([
            'comparison_config_id' => $config->id,
            'status' => $overallStatus,
            'matched_items' => $items,
            'completion_percentage' => $completionPercentage,
            'summary' => $summary,
            'raw_ai_response' => json_encode($aiResponse),
            'error_message' => ! empty($extractionErrors) ? implode('; ', $extractionErrors) : null,
        ]);

        return [
            'isConfigured' => true,
            'status' => $overallStatus,
            'summary' => $summary,
            'completion_percentage' => (float) $completionPercentage,
            'items' => $items,
            ...$this->structuredFields($aiResponse, $items),
            'error_message' => ! empty($extractionErrors) ? implode('; ', $extractionErrors) : null,
            'created_at' => $result->created_at?->diffForHumans(),
        ];
    }

    protected function emptyStructuredFields(): array
    {
        return [
            'items' => [],
            'expected_items' => [],
            'completed_items' => [],
            'partial_items' => [],
            'pending_items' => [],
            'recommendations' => [],
        ];
    }

    protected function structuredFieldsFromResult(WorkflowComparisonResult $result): array
    {
        $raw = is_string($result->raw_ai_response) ? json_decode($result->raw_ai_response, true) : null;

        return $this->structuredFields(is_array($raw) ? $raw : [], $result->matched_items ?? []);
    }

    protected function structuredFields(array $aiResponse, array $items): array
    {
        $expectedItems = $this->stringList($aiResponse['expected_items'] ?? []);
        if (empty($expectedItems)) {
            $expectedItems = collect($items)
                ->pluck('requirement')
                ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                ->values()
                ->all();
        }

        return [
            'expected_items' => $expectedItems,
            'completed_items' => $this->stringList($aiResponse['completed_items'] ?? $this->itemsByStatus($items, ['completed'])),
            'partial_items' => $this->stringList($aiResponse['partial_items'] ?? $this->itemsByStatus($items, ['partially_completed', 'partial'])),
            'pending_items' => $this->stringList($aiResponse['pending_items'] ?? $this->itemsByStatus($items, ['missing', 'unclear'])),
            'recommendations' => $this->stringList($aiResponse['recommendations'] ?? []),
        ];
    }

    protected function itemsByStatus(array $items, array $statuses): array
    {
        return collect($items)
            ->filter(fn ($item) => is_array($item) && in_array($item['status'] ?? null, $statuses, true))
            ->map(fn ($item) => $item['requirement'] ?? $item['matched_deliverable'] ?? null)
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->values()
            ->all();
    }

    protected function stringList(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => is_string($value) ? trim($value) : null)
            ->filter()
            ->values()
            ->all();
    }
}
