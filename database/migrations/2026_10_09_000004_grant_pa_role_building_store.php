<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/* The PA's main role is "pa": like "personal-assistant", it may view the Building Materials Store (and its values). */
return new class extends Migration {
    public function up(): void
    {
        $storeId = DB::table('inventory_stores')->where('code', 'building')->value('id');
        $roleId = DB::table('roles')->where('code', 'pa')->value('id');
        if (! $storeId || ! $roleId) return;
        DB::table('inventory_store_role')->updateOrInsert(['inventory_store_id' => $storeId, 'role_id' => $roleId],
            ['can_view' => 1, 'can_receive' => 0, 'can_issue' => 0, 'can_adjust' => 0, 'can_view_value' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function down(): void
    {
        $storeId = DB::table('inventory_stores')->where('code', 'building')->value('id');
        $roleId = DB::table('roles')->where('code', 'pa')->value('id');
        if ($storeId && $roleId) DB::table('inventory_store_role')->where(['inventory_store_id' => $storeId, 'role_id' => $roleId])->delete();
    }
};
