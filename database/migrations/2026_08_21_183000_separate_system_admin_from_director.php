<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $adminRoleId = DB::table('roles')->where('code', 'system-admin')->value('id');

        if (! $adminRoleId) {
            $adminRoleId = DB::table('roles')->insertGetId([
                'code' => 'system-admin',
                'name' => 'System Administrator',
                'description' => 'Maintains accounts, permissions, security and system configuration. Has no Director business authority.',
                'is_superuser' => true,
                'rank' => 5,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('roles')->where('id', $adminRoleId)->update([
                'name' => 'System Administrator',
                'description' => 'Maintains accounts, permissions, security and system configuration. Has no Director business authority.',
                'is_superuser' => true,
                'is_active' => true,
                'updated_at' => $now,
            ]);
        }

        $admin = DB::table('users')->where('username', 'admin')->first();
        if ($admin) {
            DB::table('users')->where('id', $admin->id)->update([
                'role_id' => $adminRoleId,
                'updated_at' => $now,
            ]);
            DB::table('role_user')->where('user_id', $admin->id)->delete();
            DB::table('role_user')->insert([
                'user_id' => $admin->id,
                'role_id' => $adminRoleId,
                'is_primary' => true,
                'assigned_by' => $admin->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $moduleIds = DB::table('modules')->whereIn('key', [
            'dashboard', 'hr', 'security', 'settings', 'audit', 'messages', 'notifications',
        ])->pluck('id');
        foreach ($moduleIds as $moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $adminRoleId, 'module_id' => $moduleId],
                ['can_view'=>true, 'can_create'=>true, 'can_edit'=>true, 'can_delete'=>true, 'can_approve'=>false, 'created_at'=>$now, 'updated_at'=>$now]
            );
        }
    }

    public function down(): void
    {
        // Account-role separation is intentionally not reversed: restoring
        // technical Admin as Director would recreate the security conflict.
    }
};
