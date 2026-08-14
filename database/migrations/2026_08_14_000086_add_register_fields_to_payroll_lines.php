<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('payroll_lines', function (Blueprint $table) { $table->decimal('sacco',16,2)->default(0); $table->decimal('performance_adjustment',16,2)->default(0); $table->decimal('arrears',16,2)->default(0); $table->string('payment_status',20)->default('pending'); }); }
    public function down(): void { Schema::table('payroll_lines', fn (Blueprint $table) => $table->dropColumn(['sacco','performance_adjustment','arrears','payment_status'])); }
};
