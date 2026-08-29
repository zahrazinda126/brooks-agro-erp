<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $products = [
            ['code' => 'MANURE-POULTRY-KG', 'name' => 'Poultry manure', 'unit' => 'kg'],
            ['code' => 'MANURE-GOAT-KG', 'name' => 'Goat manure', 'unit' => 'kg'],
            ['code' => 'MANURE-RABBIT-KG', 'name' => 'Rabbit manure', 'unit' => 'kg'],
            ['code' => 'MANURE-BAG', 'name' => 'Mixed farm manure', 'unit' => 'bag'],
        ];

        foreach ($products as $product) {
            DB::table('sale_products')->insertOrIgnore([
                $product + ['category' => 'manure', 'is_active' => true, 'updated_at' => $now, 'created_at' => $now],
            ]);
            DB::table('sale_products')->where('code', $product['code'])->update([
                'category' => 'manure', 'is_active' => true, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Catalogue records may already be referenced by sales, so rollback
        // deliberately leaves them in place rather than breaking history.
    }
};
