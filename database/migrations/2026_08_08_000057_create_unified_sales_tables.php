<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_customers', function (Blueprint $t) {
            $t->id(); $t->string('name',160); $t->string('phone',40)->nullable();
            $t->string('email',160)->nullable(); $t->text('address')->nullable();
            $t->string('tax_number',80)->nullable(); $t->decimal('credit_limit',16,2)->default(0);
            $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('sale_products', function (Blueprint $t) {
            $t->id(); $t->string('code',50)->unique(); $t->string('name',160);
            $t->string('category',40)->index(); $t->string('unit',40);
            $t->decimal('unit_price',16,2)->default(0); $t->decimal('stock_quantity',16,2)->nullable();
            $t->foreignId('egg_grade_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_active')->default(true); $t->text('notes')->nullable(); $t->timestamps();
        });
        Schema::create('sales', function (Blueprint $t) {
            $t->id(); $t->string('invoice_number',50)->unique(); $t->date('sale_date')->index();
            $t->foreignId('customer_id')->nullable()->constrained('sales_customers')->nullOnDelete();
            $t->string('customer_name',160); $t->string('payment_type',20);
            $t->string('payment_method',30)->nullable(); $t->string('farm_account',120)->nullable();
            $t->decimal('subtotal',16,2); $t->decimal('discount',16,2)->default(0);
            $t->decimal('total',16,2); $t->decimal('amount_paid',16,2)->default(0);
            $t->decimal('balance',16,2)->default(0); $t->string('status',25)->default('completed');
            $t->text('notes')->nullable(); $t->foreignId('sold_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('sale_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->nullable()->constrained('sale_products')->nullOnDelete();
            $t->string('description',180); $t->string('unit',40); $t->decimal('quantity',16,2);
            $t->decimal('unit_price',16,2); $t->decimal('line_total',16,2); $t->timestamps();
        });
        Schema::create('sales_payments', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->date('paid_on'); $t->decimal('amount',16,2); $t->string('payment_method',30);
            $t->string('farm_account',120); $t->string('reference',100)->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });
        Schema::create('sales_returns', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->restrictOnDelete();
            $t->foreignId('sale_line_id')->constrained()->restrictOnDelete(); $t->date('returned_on');
            $t->decimal('quantity',16,2); $t->decimal('refund_amount',16,2)->default(0);
            $t->text('reason'); $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });

        foreach (DB::table('egg_grades')->where('is_active',true)->orderBy('sort_order')->get() as $grade) {
            DB::table('sale_products')->insert([
                'code'=>'EGG-'.strtoupper($grade->code), 'name'=>$grade->name.' eggs', 'category'=>'eggs',
                'unit'=>'tray', 'unit_price'=>$grade->default_price, 'stock_quantity'=>null,
                'egg_grade_id'=>$grade->id, 'is_active'=>true, 'notes'=>$grade->eggs_per_tray.' eggs per tray',
                'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }

        $roleId=DB::table('roles')->where('code','sales-manager')->value('id');
        foreach (DB::table('modules')->whereIn('key',['sales','customers','invoices','receipts','returns','customer-ledger','inventory','reports'])->get() as $module) {
            if ($roleId) DB::table('module_role')->updateOrInsert(
                ['role_id'=>$roleId,'module_id'=>$module->id],
                ['can_view'=>true,'can_create'=>true,'can_edit'=>true,'can_delete'=>false,'can_approve'=>false,'created_at'=>now(),'updated_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns'); Schema::dropIfExists('sales_payments');
        Schema::dropIfExists('sale_lines'); Schema::dropIfExists('sales');
        Schema::dropIfExists('sale_products'); Schema::dropIfExists('sales_customers');
    }
};
