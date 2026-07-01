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
use Illuminate\Support\Facades\DB;

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
        // Check if AI is configured
        if (! $this->aiService->isConfigured()) {
            return $this->storeConfigMissingResult($context);
        }

        // Get or create comparison config
        $config = $this->getOrCreateConfig($context);

        // Get requirement files (file_category = 'requirement')
        $requirementFiles = $this->getRequirementFiles($context);

        // Get deliverable files (file_category = 'evidence')
        $deliverableFiles = $this->getDeliverableFiles($context);

        if (empty($requirementFiles)) {
            return [
                'isConfigured' => true,
                'status' => 'no_requirements',
                'summary' => 'No requirement files uploaded yet. Upload a requirement file (PDF, DOCX, TXT, CSV, XLSX) with category "requirement".',
                'completion_percentage' => 0,
                'items' => [],
            ];
        }

        if (empty($deliverableFiles)) {
            return [
                'isConfigured' => true,
                'status' => 'no_deliverables',
                'summary' => 'No deliverable/evidence files uploaded yet. Upload evidence files to compare against requirements.',
                'completion_percentage' => 0,
                'items' => [],
            ];
        }

        // Extract text from requirement files
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
                    $requirement = WorkflowRequirement::create([
                        'comparison_config_id' => $config->id,
                        'workflow_file_id' => $file->id,
                        'requirement_text' => $req['text'],
                        'source_page' => $req['page'] ?? null,
                    ]);
                    $requirements[] = $requirement;
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
                'items' => [],
            ];
        }

        // Extract text from deliverable files
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
                    $deliverable = WorkflowDeliverable::create([
                        'comparison_config_id' => $config->id,
                        'workflow_file_id' => $file->id,
                        'deliverable_text' => $del['text'],
                        'source_page' => $del['page'] ?? null,
                    ]);
                    $deliverables[] = $deliverable;
                }
            }
        }

        // Compare requirements vs deliverables
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
        if ($context instanceof Subtask) {
            return WorkflowFile::where('subtask_id', $context->id)
                ->whereIn('file_category', ['evidence', 'attachment'])
                ->orderBy('created_at')
                ->get()
                ->all();
        }
        if ($context instanceof Task) {
            return WorkflowFile::where('task_id', $context->id)
                ->whereIn('file_category', ['evidence', 'attachment'])
                ->orderBy('created_at')
                ->get()
                ->all();
        }

        return WorkflowFile::where('project_id', $context->id)
            ->whereNull('task_id')
            ->whereNull('subtask_id')
            ->whereIn('file_category', ['evidence', 'attachment'])
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    protected function storeConfigMissingResult(Project|Task|Subtask $context): array
    {
        $config = $this->getOrCreateConfig($context);

        $result = WorkflowComparisonResult::create([
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
            'items' => [],
        ];
    }

    protected function storeFailedResult(WorkflowComparisonConfig $config, array $aiResponse): array
    {
        $result = WorkflowComparisonResult::create([
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
            'items' => [],
            'error_message' => $aiResponse['error'] ?? 'Unknown error',
        ];
    }

    protected function storeSuccessResult(WorkflowComparisonConfig $config, array $aiResponse, array $extractionErrors = []): array
    {
        $items = $aiResponse['items'] ?? [];
        $completionPercentage = $aiResponse['completion_percentage'] ?? 0;
        $summary = $aiResponse['summary'] ?? '';

        // Determine overall status from items
        $statuses = collect($items)->pluck('status')->filter();
        $overallStatus = 'partially_completed';
        if ($statuses->every(fn ($s) => $s === 'completed')) {
            $overallStatus = 'completed';
        } elseif ($statuses->every(fn ($s) => in_array($s, ['missing', 'unclear']))) {
            $overallStatus = 'missing';
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
            'error_message' => ! empty($extractionErrors) ? implode('; ', $extractionErrors) : null,
            'created_at' => $result->created_at?->diffForHumans(),
        ];
    }
}
