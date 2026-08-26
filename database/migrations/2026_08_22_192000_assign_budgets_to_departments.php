<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index(['department_id', 'period_start', 'status']);
        });

        $departmentIds = DB::table('departments')->pluck('id', 'code');
        foreach (DB::table('budgets')->get(['id', 'category']) as $budget) {
            $category = strtolower($budget->category);
            $code = str_contains($category, 'staff welfare') ? 'hr'
                : (collect(['poultry', 'goat', 'rabbit', 'dog', 'garden', 'kituntu'])->contains(fn ($word) => str_contains($category, $word)) ? 'production'
                : (str_contains($category, 'sales') ? 'sales'
                : (str_contains($category, 'administration') ? 'administration' : 'finance')));
            DB::table('budgets')->where('id', $budget->id)->update(['department_id' => $departmentIds[$code] ?? null]);
        }

        $moduleId = DB::table('modules')->where('key', 'budget')->value('id');
        if ($moduleId) {
            foreach (DB::table('roles')->whereIn('code', ['production-manager', 'finance-manager', 'hr-manager', 'department-manager'])->get() as $role) {
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $role->id, 'module_id' => $moduleId],
                    ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'can_approve' => false, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropIndex(['department_id', 'period_start', 'status']);
            $table->dropColumn('department_id');
        });
    }
};
