<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->foreignId('animal_group_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('poultry_house_id')->nullable()->after('animal_group_id')->constrained('poultry_houses')->nullOnDelete();
            $table->index(['poultry_house_id', 'created_at'], 'sale_lines_house_history');
        });
    }

    public function down(): void
    {
        Schema::table('sale_lines', function (Blueprint $table) {
            $table->dropIndex('sale_lines_house_history');
            $table->dropConstrainedForeignId('poultry_house_id');
            $table->dropConstrainedForeignId('animal_group_id');
        });
    }
};
