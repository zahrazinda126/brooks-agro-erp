<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Construction, run by the PA.
 |   HR registers builders (temporary staff, no login, not on payroll) and gives them a room.
 |   The PA keeps projects: builders with their agreed pay, and materials needed.
 |   The Store Manager receives materials into the Building Materials Store and issues them to a project.
 |   Finance pays builders and suppliers through its normal entry point, choosing the project.
 */
return new class extends Migration {
    public function up(): void
    {
        // Builders are fed and housed like farm workers but are not paid through payroll.
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('on_payroll')->default(true)->after('pay_frequency');
        });

        Schema::create('construction_projects', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('location', 160)->nullable();
            $table->date('starts_on');
            $table->date('expected_end_on')->nullable();
            $table->string('status', 20)->default('active'); // active, finished
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('construction_project_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('pay_basis', 10)->default('day');   // day, job
            $table->decimal('agreed_amount', 14, 2)->default(0);
            $table->date('from_on')->nullable();
            $table->date('to_on')->nullable();
            $table->timestamps();
            $table->unique(['construction_project_id', 'employee_id']);
        });

        Schema::create('construction_materials_needed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('construction_project_id')->constrained()->cascadeOnDelete();
            $table->string('item', 160);
            $table->decimal('quantity', 14, 2);
            $table->string('unit', 40)->nullable();
            $table->decimal('estimated_cost', 16, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Materials issued from the store to a project; payments made for a project.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('construction_project_id')->nullable()->after('poultry_house_id')->constrained()->nullOnDelete();
        });
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignId('construction_project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('construction_worker_id')->nullable()->constrained('employees')->nullOnDelete();
        });

        // The Building Materials Store, with the same access pattern as the other stores.
        $storeId = DB::table('inventory_stores')->insertGetId(['code' => 'building', 'name' => 'Building Materials Store', 'section' => 'Administration',
            'description' => 'Construction materials received for farm building projects and issued to each project.', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $grants = ['director' => [1, 1, 1, 1, 1], 'ceo' => [1, 1, 1, 1, 1], 'system-administrator' => [1, 1, 1, 1, 1], 'inventory-officer' => [1, 1, 1, 1, 1],
            'store-keeper' => [1, 1, 1, 0, 0], 'finance-manager' => [1, 0, 0, 0, 1], 'personal-assistant' => [1, 0, 0, 0, 1]];
        foreach ($grants as $roleCode => $p) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if ($roleId) DB::table('inventory_store_role')->insert(['inventory_store_id' => $storeId, 'role_id' => $roleId, 'can_view' => $p[0], 'can_receive' => $p[1], 'can_issue' => $p[2], 'can_adjust' => $p[3], 'can_view_value' => $p[4], 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('inventory_stores')->where('code', 'building')->delete();
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('construction_worker_id');
            $table->dropConstrainedForeignId('construction_project_id');
        });
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->dropConstrainedForeignId('construction_project_id'));
        Schema::dropIfExists('construction_materials_needed');
        Schema::dropIfExists('construction_project_workers');
        Schema::dropIfExists('construction_projects');
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn('on_payroll'));
    }
};
