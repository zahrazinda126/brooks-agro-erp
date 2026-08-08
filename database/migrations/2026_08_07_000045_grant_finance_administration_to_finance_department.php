<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $departmentIds = DB::table('departments')
            ->whereIn('code', ['finance', 'div-finance'])
            ->pluck('id');

        $moduleIds = DB::table('modules')
            ->whereIn('key', [
                'approvals', 'audit', 'chart-of-accounts', 'finance-periods',
                'finance-accounts', 'payment-methods', 'document-numbering',
                'tax-settings', 'budget-settings', 'finance-imports',
            ])
            ->pluck('id');

        foreach ($departmentIds as $departmentId) {
            foreach ($moduleIds as $moduleId) {
                DB::table('department_module')->updateOrInsert(
                    ['department_id' => $departmentId, 'module_id' => $moduleId],
                    [
                        'can_view' => true,
                        'can_create' => true,
                        'can_edit' => true,
                        'can_delete' => false,
                        'can_approve' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        // Permissions are retained to avoid removing any pre-existing grants.
    }
};
