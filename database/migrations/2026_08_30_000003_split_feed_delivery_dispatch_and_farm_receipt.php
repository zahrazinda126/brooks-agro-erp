<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('feed_delivery_verifications', function (Blueprint $table) {
            $table->string('status', 30)->default('awaiting_farm_receipt')->after('notes')->index();
            $table->foreignId('supplier_recorded_by')->nullable()->after('recorded_by')->constrained('users')->nullOnDelete();
            $table->foreignId('farm_received_by')->nullable()->after('supplier_recorded_by')->constrained('users')->nullOnDelete();
            $table->timestamp('supplier_recorded_at')->nullable()->after('farm_received_by');
            $table->timestamp('farm_received_at')->nullable()->after('supplier_recorded_at');
        });
        Schema::table('feed_delivery_bags', function (Blueprint $table) {
            $table->decimal('farm_weight_kg', 10, 2)->nullable()->change();
            $table->decimal('variance_kg', 10, 2)->nullable()->change();
        });
        DB::table('feed_delivery_verifications')->update([
            'status'=>'received_at_farm',
            'supplier_recorded_by'=>DB::raw('recorded_by'),
            'farm_received_by'=>DB::raw('recorded_by'),
            'supplier_recorded_at'=>DB::raw('created_at'),
            'farm_received_at'=>DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('feed_delivery_bags', function (Blueprint $table) {
            $table->decimal('farm_weight_kg', 10, 2)->nullable(false)->change();
            $table->decimal('variance_kg', 10, 2)->nullable(false)->change();
        });
        Schema::table('feed_delivery_verifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_recorded_by');
            $table->dropConstrainedForeignId('farm_received_by');
            $table->dropColumn(['status','supplier_recorded_at','farm_received_at']);
        });
    }
};
