<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['code' => 'personal-assistant'],
            ['name' => 'Personal Assistant', 'description' => 'Executive support and coordination without approval authority.',
             'is_superuser' => false, 'rank' => 35, 'created_at' => now(), 'updated_at' => now()]
        );

        $roleId = DB::table('roles')->where('code', 'personal-assistant')->value('id');
        $departmentId = DB::table('departments')->where('code', 'administration')->value('id');
        $permissions = ['dashboard', 'reports', 'scheduled-reports', 'approvals', 'messages', 'notifications'];

        foreach (DB::table('modules')->whereIn('key', $permissions)->get(['id', 'key']) as $module) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $module->id],
                ['can_view' => true,
                 'can_create' => $module->key === 'messages',
                 'can_edit' => $module->key === 'messages',
                 'can_delete' => false, 'can_approve' => false,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $userId = DB::table('users')->where('username', 'assistant')->value('id');
        if (! $userId) {
            $userId = DB::table('users')->insertGetId([
                'username' => 'assistant', 'name' => 'Personal Assistant', 'email' => null,
                'password' => Hash::make('BrooksPA@2026'), 'role_id' => $roleId,
                'department_id' => $departmentId, 'employee_id' => null,
                'is_active' => true, 'must_change_password' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        } else {
            DB::table('users')->where('id', $userId)->update([
                'role_id' => $roleId, 'department_id' => $departmentId,
                'is_active' => true, 'updated_at' => now(),
            ]);
        }

        DB::table('role_user')->updateOrInsert(
            ['user_id' => $userId, 'role_id' => $roleId],
            ['is_primary' => true, 'assigned_by' => DB::table('users')->where('username', 'admin')->value('id'),
             'created_at' => now(), 'updated_at' => now()]
        );
        if ($departmentId) {
            DB::table('department_user')->updateOrInsert(
                ['user_id' => $userId, 'department_id' => $departmentId],
                ['is_primary' => true, 'assigned_by' => DB::table('users')->where('username', 'admin')->value('id'),
                 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void {}
};
