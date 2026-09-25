<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vaccination_plans', function (Blueprint $table) {
            $table->foreignId('animal_group_id')->nullable()->constrained('animal_groups')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vaccination_plans', fn (Blueprint $table) => $table->dropConstrainedForeignId('animal_group_id'));
    }
};
