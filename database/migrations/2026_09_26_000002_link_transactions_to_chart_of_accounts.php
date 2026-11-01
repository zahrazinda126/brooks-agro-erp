<?php

use App\Services\ChartOfAccounts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive: loads the workbook chart of accounts and gives each transaction an optional
 * link to it. The existing free-text account columns are kept unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->string('workbook_label', 160)->nullable()->after('name');
            $table->string('statement', 20)->nullable()->after('type');
            $table->boolean('is_group')->default(false)->after('parent_id');
            $table->unsignedInteger('sort_order')->nullable()->after('is_group');
        });

        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->foreignId('finance_account_id')->nullable()->after('sub_account')->constrained('finance_accounts')->nullOnDelete();
        });

        ChartOfAccounts::seedWorkbookChart();
        ChartOfAccounts::linkTransactions();
    }

    public function down(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finance_account_id');
        });
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->dropColumn(['workbook_label', 'statement', 'is_group', 'sort_order']);
        });
    }
};
