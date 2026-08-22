<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('executive_directives', function (Blueprint $table) {
            $table->string('recipient_scope', 40)->nullable()->after('assigned_to')->index();
        });
    }

    public function down(): void
    {
        Schema::table('executive_directives', fn (Blueprint $table) => $table->dropColumn('recipient_scope'));
    }
};
