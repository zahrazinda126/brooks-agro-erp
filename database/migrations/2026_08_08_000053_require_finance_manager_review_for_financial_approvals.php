<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $approvalModuleId = DB::table('modules')->where('key', 'approvals')->value('id');
        $financeManagerRoleId = DB::table('roles')->where('code', 'finance-manager')->value('id');

        if ($approvalModuleId && $financeManagerRoleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id'=>$financeManagerRoleId, 'module_id'=>$approvalModuleId],
                ['can_view'=>true, 'can_create'=>false, 'can_edit'=>false, 'can_delete'=>false,
                 'can_approve'=>true, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }

        DB::transaction(function () {
            $pendingFinancialApprovals = DB::table('approvals')
                ->where('status', 'pending')
                ->whereIn('module_key', ['budget', 'purchases'])
                ->pluck('id');

            foreach ($pendingFinancialApprovals as $approvalId) {
                $approval = DB::table('approvals')->where('id', $approvalId)->first();
                DB::table('approval_steps')->where('approval_id', $approvalId)->delete();
                DB::table('approval_steps')->insert([
                    ['approval_id'=>$approvalId, 'stage'=>1, 'role_code'=>'finance-manager', 'status'=>'pending', 'created_at'=>now(), 'updated_at'=>now()],
                    ['approval_id'=>$approvalId, 'stage'=>2, 'role_code'=>'ceo', 'status'=>'waiting', 'created_at'=>now(), 'updated_at'=>now()],
                    ['approval_id'=>$approvalId, 'stage'=>3, 'role_code'=>'director', 'status'=>'waiting', 'created_at'=>now(), 'updated_at'=>now()],
                ]);
                DB::table('approvals')->where('id', $approvalId)->update([
                    'level'=>1, 'required_levels'=>3, 'decided_by'=>null, 'decided_at'=>null,
                    'decision_note'=>null, 'due_at'=>now()->addDays(2), 'updated_at'=>now(),
                ]);
                if ($approval?->approvable_type === \App\Models\Purchase::class) {
                    DB::table('purchases')->where('id', $approval->approvable_id)->update(['status'=>'submitted', 'updated_at'=>now()]);
                }
            }
        });
    }

    public function down(): void
    {
        // Completed and pending financial decisions must not be rewritten on rollback.
    }
};
