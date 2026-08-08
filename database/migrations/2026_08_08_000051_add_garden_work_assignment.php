<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $departmentId = DB::table('departments')->where('code', 'garden')->value('id');
        if (! $departmentId) {
            $departmentId = DB::table('departments')->insertGetId([
                'code' => 'garden',
                'name' => 'Kituntu Garden',
                'level' => 'department',
                'description' => 'Workers assigned to garden planting, inputs, irrigation, harvest and loss records.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $moduleId = DB::table('modules')->where('key', 'garden')->value('id');
        if ($moduleId) {
            DB::table('department_module')->updateOrInsert(
                ['department_id' => $departmentId, 'module_id' => $moduleId],
                [
                    'can_view' => true,
                    'can_create' => true,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_approve' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        // Preserve department assignments and operational access history.
    }
};
