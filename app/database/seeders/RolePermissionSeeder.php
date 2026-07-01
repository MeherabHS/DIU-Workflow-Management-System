<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'access admin dashboard',
            'access pm dashboard',
            'access coordinator dashboard',
            'access subordinate dashboard',
            'manage users',
            'assign roles',
            'view audit trail',
            'view reports',
            'export reports',
            'view own profile',
            'update own profile',
            'view repository',
            'create repository entry',
            'update repository entry',
            'add repository update',
            'view projects',
            'create project',
            'update project',
            'assign coordinator',
            'view assigned projects',
            'view project tasks',
            'create project task',
            'update project task',
            'create project subtask',
            'update project subtask',
            'assign subordinate',
            'revoke subordinate assignment',
            'view assigned subtasks',
            'update assigned subtask progress',
            'view workflow messages',
            'create workflow message',
            'delete workflow message',
            'view workflow files',
            'upload workflow file',
            'download workflow file',
            'delete workflow file',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissions = [
            'Admin' => $permissions,
            'PM/Manager' => [
                'access pm dashboard',
                'view own profile',
                'update own profile',
                'view repository',
                'create repository entry',
                'update repository entry',
                'add repository update',
                'view projects',
                'create project',
                'update project',
                'assign coordinator',
                'view assigned projects',
                'view project tasks',
                'create project task',
                'update project task',
                'create project subtask',
                'update project subtask',
            'view assigned subtasks',
                'update assigned subtask progress',
                'view workflow messages',
                'create workflow message',
                'view workflow files',
                'upload workflow file',
                'download workflow file',
                'delete workflow file',
                'view reports',
                'export reports',
            ],
            'Coordinator' => [
                'access coordinator dashboard',
                'view own profile',
                'update own profile',
                'view repository',
                'view assigned projects',
                'view project tasks',
                'create project task',
                'update project task',
                'create project subtask',
                'update project subtask',
                'assign subordinate',
                'revoke subordinate assignment',
                'view workflow messages',
                'create workflow message',
                'view workflow files',
                'upload workflow file',
                'download workflow file',
            ],
            'Subordinate' => [
                'access subordinate dashboard',
                'view own profile',
                'update own profile',
                'view assigned subtasks',
                'update assigned subtask progress',
                'view workflow messages',
                'create workflow message',
                'view workflow files',
                'upload workflow file',
                'download workflow file',
            ],
        ];
        foreach ($rolePermissions as $roleName => $assignedPermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->syncPermissions($assignedPermissions);
        }

        $users = [
            ['name' => 'Admin User', 'email' => 'admin@example.com', 'role' => 'Admin'],
            ['name' => 'PM User', 'email' => 'pm@example.com', 'role' => 'PM/Manager'],
            ['name' => 'Coordinator User', 'email' => 'coordinator@example.com', 'role' => 'Coordinator'],
            ['name' => 'Subordinate User', 'email' => 'subordinate@example.com', 'role' => 'Subordinate'],
        ];

        foreach ($users as $userData) {
            $user = User::query()->updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$userData['role']]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}












