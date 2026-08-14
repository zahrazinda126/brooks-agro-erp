<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $roleId = DB::table('roles')->where('code', 'production-manager')->value('id');
        if (! $roleId) return;

        $inventoryId = DB::table('modules')->where('key', 'inventory')->value('id');
        if ($inventoryId) {
            DB::table('module_role')->where(['role_id'=>$roleId, 'module_id'=>$inventoryId])->update([
                'can_view'=>false, 'can_create'=>false, 'can_edit'=>false,
                'can_delete'=>false, 'can_approve'=>false, 'updated_at'=>now(),
            ]);
        }

        $feedId = DB::table('modules')->where('key', 'feed-received')->value('id');
        if ($feedId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id'=>$roleId, 'module_id'=>$feedId],
                ['can_view'=>true, 'can_create'=>true, 'can_edit'=>true,
                 'can_delete'=>false, 'can_approve'=>false, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }
    }

    public function down(): void {}
};
