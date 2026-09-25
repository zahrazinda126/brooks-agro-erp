<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payable_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payable_id')->constrained()->cascadeOnDelete();
            $table->string('item_name', 160);
            $table->decimal('quantity', 14, 2);
            $table->string('unit', 40);
            $table->decimal('amount', 16, 2);
            $table->timestamps();
        });

        DB::table('payables')->orderBy('id')->each(function ($payable) {
            DB::table('payable_lines')->insert([
                'payable_id' => $payable->id,
                'item_name' => $payable->item_name ?: ($payable->description ?: 'Supplier item'),
                'quantity' => $payable->quantity ?: 1,
                'unit' => $payable->unit ?: 'item',
                'amount' => $payable->amount,
                'created_at' => $payable->created_at ?? now(),
                'updated_at' => $payable->updated_at ?? now(),
            ]);
        });
    }

    public function down(): void { Schema::dropIfExists('payable_lines'); }
};
