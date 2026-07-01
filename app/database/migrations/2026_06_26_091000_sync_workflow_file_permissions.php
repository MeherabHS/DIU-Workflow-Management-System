<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view workflow files',
            'upload workflow file',
            'download workflow file',
            'delete workflow file',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $assignments = [
            'Admin' => $permissions,
            'PM/Manager' => $permissions,
            'Coordinator' => ['view workflow files', 'upload workflow file', 'download workflow file'],
            'Subordinate' => ['view workflow files', 'upload workflow file', 'download workflow file'],
        ];

        foreach ($assignments as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($rolePermissions);
        }

        foreach (['Coordinator', 'Subordinate'] as $roleName) {
            Role::findOrCreate($roleName, 'web')->revokePermissionTo('delete workflow file');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $role->revokePermissionTo(['view workflow files', 'upload workflow file', 'download workflow file', 'delete workflow file']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};

