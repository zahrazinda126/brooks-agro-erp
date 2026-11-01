<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrections by reversal instead of editing or deleting: a reversal is a new entry for the
 * negative amount, linked to the original, which is marked reversed with a reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            // finance_entry = standalone Finance screen entry (reversible); finance_entry_linked = it also
            // changed a receivable, bill, salary balance, prepayment or stock receipt; null = other modules.
            $table->string('source', 30)->nullable()->after('recorded_by');
            $table->foreignId('reversal_of_id')->nullable()->after('source')->constrained('financial_transactions')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
            $table->foreignId('reversed_by')->nullable()->after('reversed_at')->constrained('users')->nullOnDelete();
            $table->string('reversal_reason', 500)->nullable()->after('reversed_by');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reversed_by');
            $table->dropConstrainedForeignId('reversal_of_id');
            $table->dropColumn(['source', 'reversed_at', 'reversal_reason']);
        });
    }
};
