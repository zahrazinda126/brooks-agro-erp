<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->string('transaction_category', 40)->nullable()->after('type');
            $table->string('account_group', 120)->nullable()->after('transaction_category');
            $table->string('entry_account', 160)->nullable()->after('account_group');
            $table->foreignId('department_id')->nullable()->after('entry_account')->constrained('departments')->nullOnDelete();
            $table->decimal('quantity', 14, 2)->nullable()->after('amount');
            $table->decimal('unit_price', 14, 2)->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['transaction_category', 'account_group', 'entry_account', 'quantity', 'unit_price']);
        });
    }
};
