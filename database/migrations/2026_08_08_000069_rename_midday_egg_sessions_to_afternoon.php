<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('egg_collections')
            ->where('session', 'midday')
            ->update(['session' => 'afternoon']);
    }

    public function down(): void
    {
        DB::table('egg_collections')
            ->where('session', 'afternoon')
            ->update(['session' => 'midday']);
    }
};
