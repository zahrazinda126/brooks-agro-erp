<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('feed_consumptions', 'query_note')) {
            Schema::table('feed_consumptions', function (Blueprint $table) {
                $table->text('query_note')->nullable()->after('approved_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('feed_consumptions', 'query_note')) {
            Schema::table('feed_consumptions', function (Blueprint $table) {
                $table->dropColumn('query_note');
            });
        }
    }
};
