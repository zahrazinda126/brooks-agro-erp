<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * A feed bill names the deliveries it charges for, so what is billed can be checked against what the
 * supplier sent and what the farm weighed. A bill that charges for more than the farm received is held
 * until the CEO approves the difference.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payable_feed_delivery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->foreignId('feed_delivery_verification_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['payable_id', 'feed_delivery_verification_id'], 'payable_feed_delivery_unique');
        });
        Schema::table('payables', function (Blueprint $table) {
            $table->foreignId('variance_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('variance_approved_at')->nullable();
            $table->text('variance_note')->nullable();
        });

        // Existing bills that quote a delivery reference are linked to that delivery.
        foreach (DB::table('feed_delivery_verifications')->whereNotNull('reference')->get(['id', 'supplier', 'reference']) as $delivery) {
            $bills = DB::table('payables')->whereRaw('lower(supplier_name) = ?', [mb_strtolower((string) $delivery->supplier)])
                ->where('description', 'like', '%'.$delivery->reference.'%')->pluck('id');
            foreach ($bills as $billId) {
                DB::table('payable_feed_delivery')->insertOrIgnore(['payable_id' => $billId, 'feed_delivery_verification_id' => $delivery->id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('payables', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variance_approved_by');
            $table->dropColumn(['variance_approved_at', 'variance_note']);
        });
        Schema::dropIfExists('payable_feed_delivery');
    }
};
