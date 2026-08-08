<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('product_id');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', fn (Blueprint $table) => $table->dropColumn('category'));
    }
};
