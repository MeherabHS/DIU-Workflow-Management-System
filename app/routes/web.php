<?php

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MySubtaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RepositoryController;
use App\Http\Controllers\SubtaskAssignmentController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\WorkflowMessageController;
use App\Http\Controllers\WorkflowFileController;
use App\Http\Controllers\WorkflowNotificationController;
use App\Http\Controllers\ComparisonController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/health', HealthController::class)->name('health');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/notifications', [WorkflowNotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{workflowNotification}/read', [WorkflowNotificationController::class, 'markRead'])
        ->middleware('throttle:notification-action')
        ->name('notifications.read');
    Route::post('/notifications/read-all', [WorkflowNotificationController::class, 'markAllRead'])
        ->middleware('throttle:notification-action')
        ->name('notifications.read-all');

    // Comparison routes (rate-limited to prevent API abuse)
    Route::post('/projects/{project}/comparison/run', [ComparisonController::class, 'run'])
        ->middleware('throttle:ai-comparison')
        ->name('projects.comparison.run');
    Route::get('/projects/{project}/comparison', [ComparisonController::class, 'show'])->name('projects.comparison.show');
    Route::post('/projects/{project}/comparison/clear', [ComparisonController::class, 'clear'])->name('projects.comparison.clear');

    Route::post('/tasks/{task}/comparison/run', [ComparisonController::class, 'run'])
        ->middleware('throttle:ai-comparison')
        ->name('tasks.comparison.run');
    Route::get('/tasks/{task}/comparison', [ComparisonController::class, 'show'])->name('tasks.comparison.show');
    Route::post('/tasks/{task}/comparison/clear', [ComparisonController::class, 'clear'])->name('tasks.comparison.clear');

    Route::post('/subtasks/{subtask}/comparison/run', [ComparisonController::class, 'run'])
        ->middleware('throttle:ai-comparison')
        ->name('subtasks.comparison.run');
    Route::get('/subtasks/{subtask}/comparison', [ComparisonController::class, 'show'])->name('subtasks.comparison.show');
    Route::post('/subtasks/{subtask}/comparison/clear', [ComparisonController::class, 'clear'])->name('subtasks.comparison.clear');

    Route::get('/admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('permission:access admin dashboard')
        ->name('admin.dashboard');

    Route::get('/pm/dashboard', [DashboardController::class, 'pm'])
        ->middleware('permission:access pm dashboard')
        ->name('pm.dashboard');

    Route::get('/coordinator/dashboard', [DashboardController::class, 'coordinator'])
        ->middleware('permission:access coordinator dashboard')
        ->name('coordinator.dashboard');

    Route::get('/subordinate/dashboard', [DashboardController::class, 'subordinate'])
        ->middleware('permission:access subordinate dashboard')
        ->name('subordinate.dashboard');

    Route::get('/my-projects', [ProjectController::class, 'mine'])->name('projects.mine');

    Route::get('/projects/{project}/assign-coordinator', [ProjectController::class, 'editCoordinatorAssignment'])
        ->name('projects.assign-coordinator.edit');
    Route::post('/projects/{project}/assign-coordinator', [ProjectController::class, 'updateCoordinatorAssignment'])
        ->name('projects.assign-coordinator.update');
    Route::post('/projects/{project}/assign-coordinator/revoke', [ProjectController::class, 'revokeCoordinatorAssignment'])
        ->name('projects.assign-coordinator.revoke');
    Route::post('/projects/{project}/finalize-to-repository', [ProjectController::class, 'finalizeToRepository'])
        ->name('projects.finalize-to-repository');
    Route::post('/projects/{project}/submit-for-review', [ProjectController::class, 'submitForReview'])
        ->name('projects.submit-for-review');

    Route::resource('projects', ProjectController::class)->except(['destroy']);

    Route::get('/projects/{project}/tasks', [TaskController::class, 'index'])->name('project.tasks.index');
    Route::get('/projects/{project}/tasks/create', [TaskController::class, 'create'])->name('project.tasks.create');
    Route::post('/projects/{project}/tasks', [TaskController::class, 'store'])->name('project.tasks.store');
    Route::get('/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::get('/tasks/{task}/edit', [TaskController::class, 'edit'])->name('tasks.edit');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');

    Route::get('/tasks/{task}/subtasks/create', [SubtaskController::class, 'create'])->name('tasks.subtasks.create');
    Route::post('/tasks/{task}/subtasks', [SubtaskController::class, 'store'])->name('tasks.subtasks.store');
    Route::get('/subtasks/{subtask}', [SubtaskController::class, 'show'])->name('subtasks.show');
    Route::get('/subtasks/{subtask}/edit', [SubtaskController::class, 'edit'])->name('subtasks.edit');
    Route::patch('/subtasks/{subtask}', [SubtaskController::class, 'update'])->name('subtasks.update');

    Route::get('/subtasks/{subtask}/assign-subordinate', [SubtaskAssignmentController::class, 'edit'])
        ->name('subtasks.assign-subordinate.edit');
    Route::post('/subtasks/{subtask}/assign-subordinate', [SubtaskAssignmentController::class, 'store'])
        ->name('subtasks.assign-subordinate.store');
    Route::post('/subtasks/{subtask}/assign-subordinate/{user}/revoke', [SubtaskAssignmentController::class, 'revoke'])
        ->name('subtasks.assign-subordinate.revoke');

    Route::get('/my-work-items', [MySubtaskController::class, 'index'])->name('my-work-items.index');
    Route::get('/my-work-items/{subtask}', [MySubtaskController::class, 'show'])->name('my-work-items.show');

    Route::get('/my-subtasks', [MySubtaskController::class, 'index'])->name('subtasks.mine');
    Route::get('/my-subtasks/{subtask}', [MySubtaskController::class, 'show'])->name('subtasks.mine.show');
    Route::patch('/my-subtasks/{subtask}/progress', [MySubtaskController::class, 'updateProgress'])->name('subtasks.mine.progress');

    Route::get('/projects/{project}/files', [WorkflowFileController::class, 'projectIndex'])
        ->whereNumber('project')
        ->name('projects.files.index');
    Route::post('/projects/{project}/files', [WorkflowFileController::class, 'projectStore'])
        ->middleware('throttle:workflow-upload')
        ->whereNumber('project')
        ->name('projects.files.store');
    Route::get('/tasks/{task}/files', [WorkflowFileController::class, 'taskIndex'])
        ->whereNumber('task')
        ->name('tasks.files.index');
    Route::post('/tasks/{task}/files', [WorkflowFileController::class, 'taskStore'])
        ->middleware('throttle:workflow-upload')
        ->whereNumber('task')
        ->name('tasks.files.store');
    Route::get('/subtasks/{subtask}/files', [WorkflowFileController::class, 'subtaskIndex'])
        ->whereNumber('subtask')
        ->name('subtasks.files.index');
    Route::post('/subtasks/{subtask}/files', [WorkflowFileController::class, 'subtaskStore'])
        ->middleware('throttle:workflow-upload')
        ->whereNumber('subtask')
        ->name('subtasks.files.store');
    Route::get('/repository/{repositoryEntry}/files', [WorkflowFileController::class, 'repositoryIndex'])
        ->whereNumber('repositoryEntry')
        ->name('repository.files.index');
    Route::post('/repository/{repositoryEntry}/files', [WorkflowFileController::class, 'repositoryStore'])
        ->middleware('throttle:workflow-upload')
        ->whereNumber('repositoryEntry')
        ->name('repository.files.store');
    Route::get('/workflow-files/{workflowFile}/download', [WorkflowFileController::class, 'download'])
        ->whereNumber('workflowFile')
        ->name('workflow-files.download');
    Route::delete('/workflow-files/{workflowFile}', [WorkflowFileController::class, 'destroy'])
        ->whereNumber('workflowFile')
        ->name('workflow-files.destroy');
    Route::get('/projects/{project}/messages', [WorkflowMessageController::class, 'projectIndex'])
        ->whereNumber('project')
        ->name('projects.messages.index');
    Route::post('/projects/{project}/messages', [WorkflowMessageController::class, 'projectStore'])
        ->middleware('throttle:workflow-message')
        ->whereNumber('project')
        ->name('projects.messages.store');
    Route::get('/tasks/{task}/messages', [WorkflowMessageController::class, 'taskIndex'])
        ->whereNumber('task')
        ->name('tasks.messages.index');
    Route::post('/tasks/{task}/messages', [WorkflowMessageController::class, 'taskStore'])
        ->middleware('throttle:workflow-message')
        ->whereNumber('task')
        ->name('tasks.messages.store');
    Route::get('/subtasks/{subtask}/messages', [WorkflowMessageController::class, 'subtaskIndex'])
        ->whereNumber('subtask')
        ->name('subtasks.messages.index');
    Route::post('/subtasks/{subtask}/messages', [WorkflowMessageController::class, 'subtaskStore'])
        ->middleware('throttle:workflow-message')
        ->whereNumber('subtask')
        ->name('subtasks.messages.store');
    Route::get('/repository', [RepositoryController::class, 'index'])->name('repository.index');
    Route::get('/repository/create', [RepositoryController::class, 'create'])->name('repository.create');
    Route::post('/repository', [RepositoryController::class, 'store'])->name('repository.store');
    Route::get('/repository/{repositoryEntry}', [RepositoryController::class, 'show'])->name('repository.show');
    Route::get('/repository/{repositoryEntry}/edit', [RepositoryController::class, 'edit'])->name('repository.edit');
    Route::patch('/repository/{repositoryEntry}', [RepositoryController::class, 'update'])->name('repository.update');
    Route::post('/repository/{repositoryEntry}/updates', [RepositoryController::class, 'storeUpdate'])->name('repository.updates.store');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])
        ->middleware('throttle:profile-photo')
        ->name('profile.photo.update');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // User Management (Admin only)
    Route::prefix('admin/users')->name('admin.users.')->middleware('permission:manage users')->group(function (): void {
        Route::get('/', [UserController::class, 'index'])->name('index');
        Route::get('/create', [UserController::class, 'create'])->name('create');
        Route::post('/', [UserController::class, 'store'])->name('store');
        Route::get('/{user}', [UserController::class, 'show'])->name('show');
        Route::get('/{user}/edit', [UserController::class, 'edit'])->name('edit');
        Route::patch('/{user}', [UserController::class, 'update'])->name('update');
        Route::post('/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{user}/reset-password', [UserController::class, 'resetPassword'])->name('reset-password');
        Route::post('/{user}/photo', [UserController::class, 'updatePhoto'])
            ->middleware('throttle:profile-photo')
            ->name('photo.update');
        Route::delete('/{user}/photo', [UserController::class, 'removePhoto'])->name('photo.remove');
    });

    // Audit Trail (Admin only)
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index')->middleware('permission:view audit trail');

    // Reports (Admin + PM only)
    Route::prefix('reports')->name('reports.')->middleware(['permission:view reports', 'throttle:report-export'])->group(function (): void {
        Route::get('/', [ReportsController::class, 'index'])->name('index');
        Route::get('/project-progress', [ReportsController::class, 'projectProgress'])->name('project-progress');
        Route::get('/task-status', [ReportsController::class, 'taskStatus'])->name('task-status');
        Route::get('/coordinator-performance', [ReportsController::class, 'coordinatorPerformance'])->name('coordinator-performance');
        Route::get('/subordinate-completion', [ReportsController::class, 'subordinateCompletion'])->name('subordinate-completion');
        Route::get('/repository-preservation', [ReportsController::class, 'repositoryPreservation'])->name('repository-preservation');
        Route::get('/audit-activity', [ReportsController::class, 'auditActivity'])->name('audit-activity');
    });
});

require __DIR__.'/auth.php';
