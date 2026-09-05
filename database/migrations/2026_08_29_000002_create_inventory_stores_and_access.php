<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_stores', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 100);
            $table->string('section', 60)->index();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('inventory_store_role', function (Blueprint $table) {
            $table->foreignId('inventory_store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->boolean('can_view')->default(false);
            $table->boolean('can_receive')->default(false);
            $table->boolean('can_issue')->default(false);
            $table->boolean('can_adjust')->default(false);
            $table->boolean('can_view_value')->default(false);
            $table->timestamps();
            $table->primary(['inventory_store_id', 'role_id']);
        });
        Schema::table('inventory_items', fn (Blueprint $table) => $table->foreignId('inventory_store_id')->nullable()->after('id')->constrained()->restrictOnDelete());
        Schema::table('inventory_movements', fn (Blueprint $table) => $table->foreignId('inventory_store_id')->nullable()->after('inventory_item_id')->constrained()->restrictOnDelete());

        $now = now();
        $stores = [
            ['code'=>'feed','name'=>'Feed Store','section'=>'Production','description'=>'Poultry and livestock feeds received, mixed and issued.'],
            ['code'=>'farm','name'=>'Farm Supplies Store','section'=>'Production','description'=>'Tools, inputs, packaging, fuel and general farm supplies.'],
            ['code'=>'veterinary','name'=>'Veterinary Store','section'=>'Production','description'=>'Vaccines, medicines, biosecurity and animal-health supplies.'],
            ['code'=>'welfare','name'=>'Staff Welfare Store','section'=>'HR & Administration','description'=>'Staff welfare supplies, protective wear and non-food welfare items.'],
            ['code'=>'kitchen','name'=>'Kitchen & Food Store','section'=>'HR & Administration','description'=>'Staff food, kitchen ingredients, utensils and meal supplies.'],
            ['code'=>'office','name'=>'Office & Administration Store','section'=>'Administration','description'=>'Stationery, cleaning and office supplies.'],
        ];
        foreach ($stores as $store) DB::table('inventory_stores')->insert($store + ['created_at'=>$now,'updated_at'=>$now]);
        $ids = DB::table('inventory_stores')->pluck('id','code');
        $grants = [
            'director'=>['*'=>[1,1,1,1,1]], 'ceo'=>['*'=>[1,1,1,1,1]], 'system-administrator'=>['*'=>[1,1,1,1,1]],
            'inventory-officer'=>['*'=>[1,1,1,1,1]], 'store-keeper'=>['*'=>[1,1,1,0,0]],
            'production-manager'=>['feed'=>[1,1,1,1,1],'farm'=>[1,1,1,0,1],'veterinary'=>[1,1,1,1,1]],
            'hr-manager'=>['welfare'=>[1,1,1,1,1],'kitchen'=>[1,1,1,1,1],'office'=>[1,1,1,0,1]],
            'finance-manager'=>['*'=>[1,0,0,0,1]],
        ];
        foreach ($grants as $roleCode=>$storeGrants) {
            $roleId=DB::table('roles')->where('code',$roleCode)->value('id'); if(!$roleId) continue;
            foreach ($ids as $code=>$storeId) {
                $p=$storeGrants[$code]??$storeGrants['*']??null; if(!$p) continue;
                DB::table('inventory_store_role')->insert(['inventory_store_id'=>$storeId,'role_id'=>$roleId,'can_view'=>$p[0],'can_receive'=>$p[1],'can_issue'=>$p[2],'can_adjust'=>$p[3],'can_view_value'=>$p[4],'created_at'=>$now,'updated_at'=>$now]);
            }
        }
        $farmId=$ids['farm']??null;
        if($farmId){DB::table('inventory_items')->whereNull('inventory_store_id')->update(['inventory_store_id'=>$farmId]);DB::table('inventory_movements')->whereNull('inventory_store_id')->update(['inventory_store_id'=>$farmId]);}
    }

    public function down(): void
    {
        Schema::table('inventory_movements', fn(Blueprint $table)=>$table->dropConstrainedForeignId('inventory_store_id'));
        Schema::table('inventory_items', fn(Blueprint $table)=>$table->dropConstrainedForeignId('inventory_store_id'));
        Schema::dropIfExists('inventory_store_role'); Schema::dropIfExists('inventory_stores');
    }
};
