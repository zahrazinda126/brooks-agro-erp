<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_name', 160);
            $table->string('phone', 40)->nullable();
            $table->string('organization', 160)->nullable();
            $table->string('purpose', 250);
            $table->string('person_to_visit', 160);
            $table->date('visit_date');
            $table->timestamp('checked_in_at');
            $table->timestamp('checked_out_at')->nullable();
            $table->string('vehicle_registration', 40)->nullable();
            $table->string('items_carried', 500)->nullable();
            $table->string('pass_number', 60)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('checked_out_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['visit_date', 'checked_out_at']);
        });

        $moduleId = DB::table('modules')->where('key', 'visitors')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'key'=>'visitors', 'name'=>'Visitor Register', 'group'=>'Administration',
                'icon'=>'fa-address-book', 'sort_order'=>405, 'is_worker_module'=>false,
                'is_active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
        foreach (DB::table('roles')->whereIn('code', ['personal-assistant', 'ceo', 'director'])->get(['id','code']) as $role) {
            DB::table('module_role')->updateOrInsert(
                ['role_id'=>$role->id, 'module_id'=>$moduleId],
                ['can_view'=>true, 'can_create'=>$role->code==='personal-assistant',
                 'can_edit'=>$role->code==='personal-assistant', 'can_delete'=>false,
                 'can_approve'=>false, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }
    }

    public function down(): void { Schema::dropIfExists('visitors'); }
};
