<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $assignments = [
            'finance' => 'finance-manager',
            'production' => 'production-manager',
            'sales' => 'sales-manager',
        ];

        foreach ($assignments as $departmentCode => $roleCode) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            $departmentId = DB::table('departments')->where('code', $departmentCode)->value('id');

            if (! $roleId || ! $departmentId) {
                continue;
            }

            $users = DB::table('users')
                ->where('department_id', $departmentId)
                ->whereNull('deleted_at')
                ->where(function ($query) use ($departmentCode) {
                    $query->whereIn('username', [$departmentCode, 'kacungwa', 'tebandeke'])
                        ->orWhereExists(function ($employee) use ($departmentCode) {
                            $employee->selectRaw('1')->from('employees')
                                ->whereColumn('employees.id', 'users.employee_id')
                                ->whereRaw('lower(employees.job_title) like ?', ['%manager%'.$departmentCode.'%']);
                        });
                })->pluck('id');

            foreach ($users as $userId) {
                DB::table('users')->where('id', $userId)->update(['role_id' => $roleId, 'updated_at' => now()]);
                DB::table('role_user')->where('user_id', $userId)->update(['is_primary' => false, 'updated_at' => now()]);
                DB::table('role_user')->updateOrInsert(
                    ['user_id' => $userId, 'role_id' => $roleId],
                    ['is_primary' => true, 'assigned_by' => 1, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        // Account-role corrections are intentionally retained.
    }
};
