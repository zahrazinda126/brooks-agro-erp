<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Customer sales no longer go to Finance and the CEO for approval. Sales still waiting are treated as approved and
 * their approval requests closed, noting why. Internal farm use keeps its approval.
 */
return new class extends Migration {
    public function up(): void
    {
        $saleIds = DB::table('sales')->where('approval_status', 'pending')->where('is_staff_consumption', false)->pluck('id');
        if ($saleIds->isEmpty()) return;
        $approvalIds = DB::table('approvals')->where('module_key', 'sales')->where('status', 'pending')
            ->where('approvable_type', 'like', '%Sale')->whereIn('approvable_id', $saleIds)->pluck('id');
        $note = 'Customer sales no longer need Finance and CEO approval.';
        DB::table('approval_steps')->whereIn('approval_id', $approvalIds)->whereIn('status', ['pending', 'waiting'])->update(['status' => 'approved', 'decided_at' => now(), 'decision_note' => $note, 'updated_at' => now()]);
        DB::table('approvals')->whereIn('id', $approvalIds)->update(['status' => 'approved', 'decided_at' => now(), 'decision_note' => $note, 'updated_at' => now()]);
        DB::table('sales')->whereIn('id', $saleIds)->update(['approval_status' => 'approved', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Not reversed: the sales were real and remain counted.
    }
};
