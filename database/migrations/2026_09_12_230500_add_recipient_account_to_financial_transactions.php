<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('financial_transactions', function (Blueprint $table) {
            $table->string('recipient_account', 160)->nullable()->after('counterparty');
        });
    }

    public function down(): void
    {
        Schema::table('financial_transactions', fn (Blueprint $table) => $table->dropColumn('recipient_account'));
    }
};
