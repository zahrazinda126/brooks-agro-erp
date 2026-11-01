<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | A feed delivery is bought for one unit (poultry, goats, rabbits or dogs). Receiving it
 | at the farm adds the weighed feed to that unit's feed stock. Existing deliveries were
 | all for poultry.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('feed_delivery_verifications', function (Blueprint $table) {
            $table->string('unit_code', 20)->default('poultry')->after('delivery_date')->index();
        });
    }

    public function down(): void
    {
        Schema::table('feed_delivery_verifications', function (Blueprint $table) {
            $table->dropIndex(['unit_code']);
            $table->dropColumn('unit_code');
        });
    }
};
