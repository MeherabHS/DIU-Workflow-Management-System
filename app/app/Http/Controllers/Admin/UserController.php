<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class UserController extends Controller
{
    public function __construct(protected AuditLogService $audit)
    {
    }
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['department']);

        if ($search = $request->string('search')->toString()) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $search);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%");
            });
        }

        if ($role = $request->string('role')->toString()) {
            $query->role($role);
        }

        if ($request->has('is_active') && $request->string('is_active')->toString() === 'pending') {
            // Users with no role
            $query->doesntHave('roles');
        } elseif ($request->has('is_active') && $request->boolean('is_active') !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query->latest('updated_at')->paginate(10)->withQueryString();

        // Transform roles into simple role name strings for frontend
        $users->through(function ($user) {
            $user->roles = $user->getRoleNames()->values()->all();
            return $user;
        });

        return Inertia::render('Admin/Users/Index', [
            'pageTitle' => 'User Management',
            'pageSubtitle' => 'Create and manage user accounts and roles.',
            'primaryAction' => 'Create User',
            'users' => $users,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => ['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'],
            'filters' => $request->only(['search', 'role', 'is_active']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('Admin/Users/Create', [
            'pageTitle' => 'Create User',
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => ['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'],
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $validated = $request->validated();
        $role = $validated['role'];
        unset($validated['role']);

        $validated['password'] = Hash::make($validated['password']);
        $validated['created_by'] = $request->user()->id;

        $user = User::create($validated);
        $user->syncRoles([$role]);

        $this->audit->logUserCreated($user, $role);

        return redirect()->route('admin.users.index')->with('status', 'User created successfully.');
    }

    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['department']);
        $user->loadCount([
            'createdProjects',
            'coordinatedProjectAssignments as assigned_projects_count',
        ]);

        return Inertia::render('Admin/Users/Show', [
            'pageTitle' => 'User Details',
            'user' => $user,
        ]);
    }

    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load(['department']);

        return Inertia::render('Admin/Users/Edit', [
            'pageTitle' => 'Edit User',
            'user' => $user,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'roles' => ['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $validated = $request->validated();
        $role = $validated['role'];
        unset($validated['role']);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $changes = array_keys(array_diff_assoc($validated, $user->getOriginal()));

        // Prevent users from changing their own role
        if ($request->user()->id === $user->id && $user->getRoleNames()->first() !== $role) {
            return back()->with('error', 'You cannot change your own role.');
        }

        DB::transaction(function () use ($user, $validated, $role): void {
            $user->update($validated);
            $user->syncRoles([$role]);
        });

        $this->audit->logUserUpdated($user, ['fields' => $changes, 'role' => $role]);

        return redirect()->route('admin.users.show', $user)->with('status', 'User updated successfully.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->authorize('toggleActive', $user);

        $user->update(['is_active' => ! $user->is_active]);

        $this->audit->logUserToggled($user, $user->is_active);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('status', "User {$status} successfully.");
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->audit->logUserPasswordReset($user);

        $token = Password::broker()->createToken($user);

        // Do not expose the reset token or URL in session/flash messages
        return back()->with('status', "Password reset token generated for {$user->email}. Provide them the reset link through your admin channel.");
    }

    /**
     * Update a user's profile photo (Admin only).
     */
    public function updatePhoto(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'photo' => ['required', File::types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)],
        ]);

        try {
            // Delete old photo if exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }

            $path = $request->file('photo')->store('profile-photos', 'public');
            $user->update(['profile_photo_path' => $path]);
        } catch (Throwable $exception) {
            Log::error('Admin profile photo upload failed.', [
                'target_user_id' => $user->id,
                'actor_id' => $request->user()?->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->audit->logUserUpdated($user, ['action' => 'profile_photo_updated']);

        return back()->with('status', 'Profile photo updated.');
    }

    /**
     * Remove a user's profile photo (Admin only).
     */
    public function removePhoto(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->update(['profile_photo_path' => null]);
        }

        return back()->with('status', 'Profile photo removed.');
    }
}
