<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $products = [
            ['BIRD-BROILER', 'Broiler chicken', 'poultry', 'bird'],
            ['BIRD-LAYER', 'Layer chicken', 'poultry', 'bird'],
            ['BIRD-SPENT-LAYER', 'Spent layer', 'poultry', 'bird'],
            ['BIRD-LOCAL', 'Local chicken', 'poultry', 'bird'],
            ['BIRD-CHICK', 'Day-old chick', 'poultry', 'chick'],
            ['BIRD-PULLET', 'Pullet', 'poultry', 'bird'],
            ['BIRD-COCKEREL', 'Cockerel', 'poultry', 'bird'],

            ['GOAT-LIVE', 'Live goat', 'goats', 'animal'],
            ['GOAT-KID', 'Goat kid', 'goats', 'animal'],
            ['GOAT-BREEDING', 'Breeding goat', 'goats', 'animal'],
            ['GOAT-MEAT-KG', 'Goat meat', 'goats', 'kg'],
            ['MILK-GOAT-L', 'Goat milk', 'milk', 'litre'],

            ['RABBIT-LIVE', 'Live rabbit', 'rabbits', 'animal'],
            ['RABBIT-BREEDING', 'Breeding rabbit', 'rabbits', 'animal'],
            ['RABBIT-MEAT-KG', 'Rabbit meat', 'rabbits', 'kg'],

            ['DOG-PUPPY', 'Puppy', 'dogs', 'animal'],
            ['DOG-TRAINED', 'Trained dog', 'dogs', 'animal'],
            ['DOG-BREEDING', 'Breeding dog', 'dogs', 'animal'],

            ['MANURE-POULTRY-KG', 'Poultry manure', 'manure', 'kg'],
            ['MANURE-GOAT-KG', 'Goat manure', 'manure', 'kg'],
            ['MANURE-RABBIT-KG', 'Rabbit manure', 'manure', 'kg'],
            ['MANURE-BAG', 'Mixed farm manure', 'manure', 'bag'],

            ['GARDEN-TOMATO-KG', 'Tomatoes', 'garden', 'kg'],
            ['GARDEN-ONION-KG', 'Onions', 'garden', 'kg'],
            ['GARDEN-CABBAGE', 'Cabbage', 'garden', 'head'],
            ['GARDEN-GREENS-BUNDLE', 'Leafy vegetables', 'garden', 'bundle'],
            ['GARDEN-BANANA-BUNCH', 'Bananas / matooke', 'garden', 'bunch'],
            ['GARDEN-MAIZE-KG', 'Maize', 'garden', 'kg'],
            ['GARDEN-BEANS-KG', 'Beans', 'garden', 'kg'],
            ['GARDEN-FRUIT-KG', 'Farm fruit', 'garden', 'kg'],

            ['OTHER-FEED-BAG', 'Surplus animal feed', 'other', 'bag'],
            ['OTHER-SACK', 'Used sack', 'other', 'piece'],
        ];

        foreach ($products as [$code, $name, $category, $unit]) {
            DB::table('sale_products')->updateOrInsert(
                ['code'=>$code],
                ['name'=>$name, 'category'=>$category, 'unit'=>$unit, 'unit_price'=>0,
                 'stock_quantity'=>null, 'is_active'=>true,
                 'notes'=>'Set the current selling price before the first sale.',
                 'created_at'=>now(), 'updated_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        DB::table('sale_products')->whereIn('code', [
            'BIRD-BROILER','BIRD-LAYER','BIRD-SPENT-LAYER','BIRD-LOCAL','BIRD-CHICK','BIRD-PULLET','BIRD-COCKEREL',
            'GOAT-LIVE','GOAT-KID','GOAT-BREEDING','GOAT-MEAT-KG','MILK-GOAT-L',
            'RABBIT-LIVE','RABBIT-BREEDING','RABBIT-MEAT-KG','DOG-PUPPY','DOG-TRAINED','DOG-BREEDING',
            'MANURE-POULTRY-KG','MANURE-GOAT-KG','MANURE-RABBIT-KG','MANURE-BAG',
            'GARDEN-TOMATO-KG','GARDEN-ONION-KG','GARDEN-CABBAGE','GARDEN-GREENS-BUNDLE',
            'GARDEN-BANANA-BUNCH','GARDEN-MAIZE-KG','GARDEN-BEANS-KG','GARDEN-FRUIT-KG',
            'OTHER-FEED-BAG','OTHER-SACK',
        ])->delete();
    }
};
