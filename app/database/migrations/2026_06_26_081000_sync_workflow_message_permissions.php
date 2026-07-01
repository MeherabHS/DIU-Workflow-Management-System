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
            'view workflow messages',
            'create workflow message',
            'delete workflow message',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $assignments = [
            'Admin' => $permissions,
            'PM/Manager' => ['view workflow messages', 'create workflow message'],
            'Coordinator' => ['view workflow messages', 'create workflow message'],
            'Subordinate' => ['view workflow messages', 'create workflow message'],
        ];

        foreach ($assignments as $roleName => $rolePermissions) {
            $role = Role::findOrCreate($roleName, 'web');
            $role->givePermissionTo($rolePermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Admin', 'PM/Manager', 'Coordinator', 'Subordinate'] as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $role->revokePermissionTo(['view workflow messages', 'create workflow message', 'delete workflow message']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};