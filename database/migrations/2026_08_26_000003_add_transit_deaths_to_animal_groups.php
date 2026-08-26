<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->unsignedInteger('transit_deaths')->default(0)->after('quantity_free');
        });
    }

    public function down(): void
    {
        Schema::table('animal_groups', fn (Blueprint $table) => $table->dropColumn('transit_deaths'));
    }
};
