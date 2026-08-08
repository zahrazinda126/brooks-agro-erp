<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'hr-manager')->value('id');
        $moduleId = DB::table('modules')->where('key', 'approvals')->value('id');

        if ($roleId && $moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id' => $roleId, 'module_id' => $moduleId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false,
                 'can_delete' => false, 'can_approve' => false,
                 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void {}
};
