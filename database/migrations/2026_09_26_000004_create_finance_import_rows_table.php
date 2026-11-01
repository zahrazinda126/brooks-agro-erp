<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging area for the finance workbook import. Rows are held here for review
 * (duplicates, unreviewed QA status, unmatched accounts) and are never posted
 * to financial_transactions automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_import_batch_id')->constrained('finance_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->string('content_hash', 64);
            $table->date('transaction_date')->nullable();
            $table->string('transaction_type', 60)->nullable();
            $table->string('account_group', 160)->nullable();
            $table->string('account', 160)->nullable();
            $table->foreignId('finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->string('offset_account', 160)->nullable();
            $table->foreignId('offset_finance_account_id')->nullable()->constrained('finance_accounts')->nullOnDelete();
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('quantity', 14, 2)->nullable();
            $table->decimal('unit_price', 14, 2)->nullable();
            $table->string('business_partner', 160)->nullable();
            $table->string('department', 120)->nullable();
            $table->string('workbook_transaction_id', 40)->nullable();
            $table->string('qa_status', 40)->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->string('review_status', 20)->default('pending'); // pending, accepted, excluded, posted
            $table->json('original');
            $table->timestamps();
            $table->unique(['finance_import_batch_id', 'source_row']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_import_rows');
    }
};
