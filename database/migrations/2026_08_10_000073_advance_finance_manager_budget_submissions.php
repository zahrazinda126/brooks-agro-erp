<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $approvals = DB::table('approvals as a')
            ->join('approval_steps as s', function ($join) {
                $join->on('s.approval_id','=','a.id')->where('s.stage',1)->where('s.role_code','finance-manager')->where('s.status','pending');
            })
            ->where('a.module_key','budget')->where('a.status','pending')->where('a.level',1)
            ->where(function ($query) {
                $query->whereExists(function ($role) {
                    $role->selectRaw('1')->from('users as u')->join('roles as r','r.id','=','u.role_id')
                        ->whereColumn('u.id','a.submitted_by')->where('r.code','finance-manager');
                })->orWhereExists(function ($role) {
                    $role->selectRaw('1')->from('role_user as ru')->join('roles as r','r.id','=','ru.role_id')
                        ->whereColumn('ru.user_id','a.submitted_by')->where('r.code','finance-manager');
                });
            })->select('a.id','a.submitted_by','a.submitted_at')->get();

        foreach ($approvals as $approval) {
            DB::table('approval_steps')->where('approval_id',$approval->id)->where('stage',1)->update([
                'status'=>'approved','decided_by'=>$approval->submitted_by,'decided_at'=>$approval->submitted_at,
                'decision_note'=>'Finance Manager submitted and confirmed the weekly budget.','updated_at'=>now(),
            ]);
            DB::table('approval_steps')->where('approval_id',$approval->id)->where('stage',2)->where('status','waiting')->update(['status'=>'pending','updated_at'=>now()]);
            DB::table('approvals')->where('id',$approval->id)->update(['level'=>2,'updated_at'=>now()]);
        }
    }

    public function down(): void {}
};
