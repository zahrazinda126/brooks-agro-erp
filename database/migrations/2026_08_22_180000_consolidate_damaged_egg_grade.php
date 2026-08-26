<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $defects = DB::table('egg_grades')->where('is_defect', true)->orderBy('id')->get();
        if ($defects->isEmpty()) return;

        $damaged = $defects->firstWhere('code', 'damaged') ?? $defects->firstWhere('code', 'cracked') ?? $defects->first();
        $defectIds = $defects->pluck('id');
        $retiredIds = $defectIds->reject(fn ($id) => (int)$id === (int)$damaged->id)->values();

        foreach (DB::table('egg_collection_lines')->whereIn('egg_grade_id', $defectIds)->select('egg_collection_id')->distinct()->pluck('egg_collection_id') as $collectionId) {
            $totals = DB::table('egg_collection_lines')->where('egg_collection_id', $collectionId)->whereIn('egg_grade_id', $defectIds)
                ->selectRaw('sum(trays) as trays, sum(loose_eggs) as loose_eggs, sum(total_eggs) as total_eggs')->first();
            DB::table('egg_collection_lines')->where('egg_collection_id', $collectionId)->whereIn('egg_grade_id', $defectIds)->delete();
            DB::table('egg_collection_lines')->insert(['egg_collection_id'=>$collectionId, 'egg_grade_id'=>$damaged->id,
                'trays'=>$totals->trays, 'loose_eggs'=>$totals->loose_eggs, 'total_eggs'=>$totals->total_eggs,
                'created_at'=>now(), 'updated_at'=>now()]);
        }

        DB::table('egg_grades')->where('id', $damaged->id)->update([
            'code'=>'damaged', 'name'=>'Damaged eggs', 'is_defect'=>true, 'is_active'=>true,
            'sort_order'=>60, 'min_weight_g'=>null, 'max_weight_g'=>null,
            'typical_from_week'=>null, 'typical_to_week'=>null, 'updated_at'=>now(),
        ]);
        if ($retiredIds->isNotEmpty()) DB::table('egg_grades')->whereIn('id', $retiredIds)->update(['is_active'=>false, 'updated_at'=>now()]);

        $products = DB::table('sale_products')->whereIn('egg_grade_id', $defectIds)->get();
        $product = $products->firstWhere('egg_grade_id', $damaged->id) ?? $products->first();
        if ($product) {
            DB::table('sale_products')->where('id', $product->id)->update(['code'=>'EGG-DAMAGED', 'name'=>'Damaged eggs',
                'category'=>'eggs', 'unit'=>'tray', 'egg_grade_id'=>$damaged->id, 'is_active'=>true,
                'notes'=>'May be sold by tray or as individual eggs.', 'updated_at'=>now()]);
            $retiredProducts = $products->pluck('id')->reject(fn ($id) => (int)$id === (int)$product->id);
            if ($retiredProducts->isNotEmpty()) DB::table('sale_products')->whereIn('id', $retiredProducts)->update(['is_active'=>false, 'updated_at'=>now()]);
        }
    }

    public function down(): void
    {
        // Consolidated historical totals cannot be reliably split back into subjective defect subtypes.
    }
};
