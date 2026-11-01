<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Inventory on the Brooks Farm Hub "Warehouse" model (decided 24 Oct 2026):
 | - stock requests: Request → Approval → Prepared → Dispatched → Received → Acknowledged,
 |   approved by the head of the requester's department;
 | - batches with expiry dates for Feed, Veterinary, Kitchen & Food and Farm Supplies
 |   (first to expire goes out first; expired stock is never issued);
 | - adjustments and losses wait for the Finance Manager's approval before they change a
 |   balance (receipt_status "awaiting_approval" → "confirmed" or "rejected").
 | Existing stores, items, movements and store access are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_store_id')->constrained()->cascadeOnDelete();
            $table->string('batch_code', 80);
            $table->date('expiry_date')->index();
            $table->date('received_on');
            $table->decimal('quantity_received', 14, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['inventory_item_id', 'expiry_date']);
        });

        Schema::create('inventory_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('inventory_store_id')->constrained();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('approver_role', 40);
            $table->date('needed_on')->nullable();
            $table->string('purpose', 500);
            $table->string('status', 20)->default('requested')->index();
            $table->text('decision_note')->nullable();
            foreach (['approved', 'prepared', 'dispatched', 'received', 'acknowledged'] as $step) {
                $table->foreignId($step.'_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp($step.'_at')->nullable();
            }
            $table->text('receipt_note')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_request_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained();
            $table->decimal('quantity_requested', 14, 2);
            $table->decimal('quantity_issued', 14, 2)->nullable();
            $table->decimal('quantity_received', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreignId('inventory_batch_id')->nullable()->after('inventory_store_id')->constrained()->nullOnDelete();
            $table->foreignId('inventory_request_id')->nullable()->after('inventory_batch_id')->constrained()->nullOnDelete();
            $table->string('adjustment_reason', 30)->nullable()->after('movement_type');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_request_id');
            $table->dropConstrainedForeignId('inventory_batch_id');
            $table->dropColumn('adjustment_reason');
        });
        Schema::dropIfExists('inventory_request_lines');
        Schema::dropIfExists('inventory_requests');
        Schema::dropIfExists('inventory_batches');
    }
};
