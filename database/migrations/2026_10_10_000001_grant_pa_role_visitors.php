<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The PA (role "pa") keeps the Director's visitor register: check visitors in and out, like "personal-assistant".
 * The Director views the list only (like the CEO); they do not record visitors.
 */
return new class extends Migration {
    private const GRANTS = [
        'pa' => ['can_view' => true, 'can_create' => true, 'can_edit' => true],
        'director' => ['can_view' => true, 'can_create' => false, 'can_edit' => false],
    ];

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('key', 'visitors')->value('id');
        if (! $moduleId) return;
        foreach (self::GRANTS as $code => $rights) {
            $roleId = DB::table('roles')->where('code', $code)->value('id');
            if (! $roleId) continue;
            DB::table('module_role')->updateOrInsert(['role_id' => $roleId, 'module_id' => $moduleId],
                $rights + ['can_delete' => false, 'can_approve' => false, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $moduleId = DB::table('modules')->where('key', 'visitors')->value('id');
        $roleIds = DB::table('roles')->whereIn('code', array_keys(self::GRANTS))->pluck('id');
        if ($moduleId) DB::table('module_role')->where('module_id', $moduleId)->whereIn('role_id', $roleIds)->delete();
    }
};
