<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->dropUnique('feed_use_unique');
            $table->foreignId('poultry_house_id')->nullable()->after('animal_group_id')
                ->constrained('poultry_houses')->nullOnDelete();
            $table->unique(['poultry_house_id', 'used_on', 'feed_type_id'], 'feed_house_use_unique');
        });
    }

    public function down(): void
    {
        Schema::table('feed_consumptions', function (Blueprint $table) {
            $table->dropUnique('feed_house_use_unique');
            $table->dropConstrainedForeignId('poultry_house_id');
            $table->unique(['animal_group_id', 'used_on', 'feed_type_id'], 'feed_use_unique');
        });
    }
};
