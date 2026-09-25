<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('payables', function (Blueprint $table) {
            $table->string('item_name', 160)->nullable()->after('description');
            $table->decimal('quantity', 14, 2)->nullable()->after('item_name');
            $table->string('unit', 40)->nullable()->after('quantity');
        });
    }
    public function down(): void { Schema::table('payables', fn (Blueprint $table) => $table->dropColumn(['item_name','quantity','unit'])); }
};
