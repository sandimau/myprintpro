<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        $access = Permission::findOrCreate('sop_access', 'web');
        $manage = Permission::findOrCreate('sop_manage', 'web');

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if (strtolower($role->name) !== 'konten') {
                $role->givePermissionTo($access);
            }
            if (in_array(strtolower($role->name), ['super', 'admin', 'manager'], true)) {
                $role->givePermissionTo($manage);
            }
        }
    }

    public function down(): void
    {
        // Penugasan hak akses yang sudah diatur secara manual tidak dihapus.
    }
};
