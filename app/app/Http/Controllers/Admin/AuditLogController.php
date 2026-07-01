<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view audit trail'), 403);

        $query = AuditLog::query()->with(['actor', 'project']);

        if ($action = $request->string('action')->toString()) {
            $query->where('action', $action);
        }

        if ($entityType = $request->string('entity_type')->toString()) {
            $query->where('entity_type', $entityType);
        }

        if ($actorId = $request->integer('actor_id')) {
            $query->where('actor_id', $actorId);
        }

        if ($projectId = $request->integer('project_id')) {
            $query->where('project_id', $projectId);
        }

        if ($dateFrom = $request->string('date_from')->toString()) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->string('date_to')->toString()) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $actions = AuditLog::query()->distinct()->pluck('action')->sort()->values();
        $entityTypes = AuditLog::query()->whereNotNull('entity_type')->distinct()->pluck('entity_type')->sort()->values();
        $actors = User::query()->orderBy('name')->get(['id', 'name']);
        $projects = Project::query()->orderBy('title')->get(['id', 'title']);

        return Inertia::render('Admin/AuditLogs/Index', [
            'pageTitle' => 'Audit Trail',
            'pageSubtitle' => 'System activity log for governance and accountability.',
            'logs' => $query->latest('created_at')->paginate(20)->withQueryString(),
            'actions' => $actions,
            'entityTypes' => $entityTypes,
            'actors' => $actors,
            'projects' => $projects,
            'filters' => $request->only(['action', 'entity_type', 'actor_id', 'project_id', 'date_from', 'date_to']),
        ]);
    }
}
