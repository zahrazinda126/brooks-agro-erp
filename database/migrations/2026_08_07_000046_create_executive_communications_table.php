<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executive_communications', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30)->default('message');
            $table->string('subject', 180);
            $table->text('body');
            $table->string('audience', 80)->default('management');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        $moduleIds = DB::table('modules')->whereIn('key', ['messages', 'notifications'])->pluck('id');
        $roleIds = DB::table('roles')->whereIn('code', ['ceo', 'director'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($moduleIds as $moduleId) {
                DB::table('module_role')->updateOrInsert(
                    ['role_id' => $roleId, 'module_id' => $moduleId],
                    ['can_view'=>true, 'can_create'=>true, 'can_edit'=>false, 'can_delete'=>false, 'can_approve'=>false, 'created_at'=>now(), 'updated_at'=>now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_communications');
    }
};
