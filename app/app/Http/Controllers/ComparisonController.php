<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Services\RequirementDeliverableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ComparisonController extends Controller
{
    public function __construct(
        protected RequirementDeliverableService $comparisonService,
    ) {}

    public function run(Request $request): JsonResponse
    {
        // Validate request body to prevent oversized payloads
        $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $context = $this->resolveContext($request);
        $this->authorizeComparison($context);

        $result = $this->comparisonService->processComparison($context);

        return response()->json($result);
    }

    public function show(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request);
        $this->authorizeComparison($context);

        $result = $this->comparisonService->getComparisonResult($context);

        return response()->json([
            'isConfigured' => $this->comparisonService->isAiConfigured(),
            'result' => $result,
        ]);
    }

    public function clear(Request $request): RedirectResponse
    {
        $context = $this->resolveContext($request);
        $this->authorizeComparison($context);

        $this->comparisonService->processComparison($context);

        return back()->with('status', 'Comparison re-run initiated.');
    }

    protected function resolveContext(Request $request): Project|Task|Subtask
    {
        if ($request->route('project')) {
            return Project::findOrFail($request->route('project'));
        }
        if ($request->route('task')) {
            return Task::findOrFail($request->route('task'));
        }
        if ($request->route('subtask')) {
            return Subtask::findOrFail($request->route('subtask'));
        }

        abort(404, 'Context not found.');
    }

    protected function authorizeComparison(Project|Task|Subtask $context): void
    {
        $user = request()->user();

        if ($user->hasAnyRole(['Admin', 'PM/Manager'])) {
            return;
        }

        abort(403, 'Unauthorized to view comparison.');
    }
}


