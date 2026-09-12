<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('approval_status', 30)->default('approved')->index()->after('status');
            $table->boolean('is_staff_consumption')->default(false)->after('approval_status');
            $table->string('consumed_by', 180)->nullable()->after('is_staff_consumption');
        });

        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();
            $table->string('role_code', 50)->index();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['role_code', 'starts_on', 'ends_on'], 'approval_delegation_period');
        });

        Schema::create('batch_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->string('batch_type', 30)->default('poultry');
            $table->string('supplier_name', 160);
            $table->string('breed', 120)->nullable();
            $table->unsignedInteger('quantity_ordered');
            $table->decimal('order_total', 16, 2);
            $table->date('ordered_on');
            $table->date('expected_on')->nullable();
            $table->string('status', 30)->default('ordered')->index();
            $table->foreignId('animal_group_id')->nullable()->constrained('animal_groups')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('batch_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_order_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 16, 2);
            $table->string('payment_method', 30);
            $table->string('farm_account', 120);
            $table->string('reference', 100)->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        $moduleId = DB::table('modules')->where('key', 'approvals')->value('id');
        foreach (DB::table('roles')->whereIn('code', ['finance-manager', 'ceo', 'director'])->get(['id']) as $role) {
            if ($moduleId) DB::table('module_role')->updateOrInsert(
                ['role_id'=>$role->id, 'module_id'=>$moduleId],
                ['can_view'=>true, 'can_create'=>true, 'can_edit'=>true, 'can_delete'=>false, 'can_approve'=>true, 'created_at'=>now(), 'updated_at'=>now()]
            );
        }

        $customersId = DB::table('modules')->where('key', 'customers')->value('id');
        $financeId = DB::table('roles')->where('code', 'finance-manager')->value('id');
        if ($customersId && $financeId) DB::table('module_role')->updateOrInsert(
            ['role_id'=>$financeId, 'module_id'=>$customersId],
            ['can_view'=>true, 'can_create'=>true, 'can_edit'=>true, 'can_delete'=>true, 'can_approve'=>false, 'created_at'=>now(), 'updated_at'=>now()]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_order_payments');
        Schema::dropIfExists('batch_orders');
        Schema::dropIfExists('approval_delegations');
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn(['approval_status', 'is_staff_consumption', 'consumed_by']));
    }
};
