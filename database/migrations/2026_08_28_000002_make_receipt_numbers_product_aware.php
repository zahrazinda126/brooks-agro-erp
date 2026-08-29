<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_payments')->orderBy('id')->each(function ($payment) {
            $categories = DB::table('sale_lines')
                ->leftJoin('sale_products', 'sale_products.id', '=', 'sale_lines.product_id')
                ->where('sale_lines.sale_id', $payment->sale_id)
                ->selectRaw('coalesce(sale_products.category, sale_lines.category) as category')
                ->pluck('category')->filter()->map(fn ($category) => strtolower($category))->unique()->values();

            $prefix = $categories->count() === 1 ? $this->prefix($categories->first()) : 'MIX';
            $newNumber = sprintf('%s-RCT-%06d', $prefix, $payment->id);
            $oldNumber = $payment->receipt_number;

            DB::table('sales_payments')->where('id', $payment->id)->update(['receipt_number' => $newNumber]);
            if ($oldNumber) {
                DB::table('financial_transactions')->where('reference', $oldNumber)->update(['reference' => $newNumber]);
            }
        });
    }

    public function down(): void
    {
        DB::table('sales_payments')->orderBy('id')->each(function ($payment) {
            $oldNumber = $payment->receipt_number;
            $restored = sprintf('RCT-%06d', $payment->id);
            DB::table('sales_payments')->where('id', $payment->id)->update(['receipt_number' => $restored]);
            DB::table('financial_transactions')->where('reference', $oldNumber)->update(['reference' => $restored]);
        });
    }

    private function prefix(string $category): string
    {
        return match ($category) {
            'eggs' => 'EGG', 'poultry' => 'PLT', 'goats' => 'GOAT',
            'rabbits' => 'RBT', 'dogs' => 'DOG', 'milk' => 'MLK',
            'manure' => 'MAN', 'garden' => 'GRD', default => 'OTH',
        };
    }
};
