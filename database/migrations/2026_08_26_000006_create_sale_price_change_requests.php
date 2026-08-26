<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sale_price_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_product_id')->constrained('sale_products')->cascadeOnDelete();
            $table->decimal('previous_unit_price', 14, 2);
            $table->decimal('proposed_unit_price', 14, 2);
            $table->decimal('previous_loose_unit_price', 14, 2)->nullable();
            $table->decimal('proposed_loose_unit_price', 14, 2)->nullable();
            $table->date('effective_from');
            $table->text('request_note')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamps();
            $table->index(['sale_product_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('sale_price_change_requests'); }
};
