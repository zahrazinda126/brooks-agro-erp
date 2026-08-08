<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fleet_records', function (Blueprint $table) {
            $table->id();
            $table->date('recorded_on')->index();
            $table->string('vehicle_id', 80)->index();
            $table->string('driver_name', 160);
            $table->dateTime('set_off_at')->nullable();
            $table->string('pickup_point', 160)->nullable();
            $table->string('destination', 160)->nullable();
            $table->unsignedInteger('people_count')->nullable();
            $table->text('purpose');
            $table->decimal('fuel_consumption_litres', 12, 2)->nullable();
            $table->string('fuel_receipt', 100)->nullable();
            $table->text('vehicle_issues')->nullable();
            $table->text('maintenance')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        $roleId=DB::table('roles')->where('code','hr-manager')->value('id');
        $moduleId=DB::table('modules')->where('key','fleet')->value('id');
        if ($roleId && $moduleId) {
            DB::table('module_role')->updateOrInsert(
                ['role_id'=>$roleId,'module_id'=>$moduleId],
                ['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,
                 'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_records');
    }
};
