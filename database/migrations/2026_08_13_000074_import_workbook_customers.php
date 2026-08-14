<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_customers')) {
            return;
        }

        $customers = [
            'Kisseka Denis', 'Nkwanga Eriab', 'Ssenabulya Vincent', 'Lukwago Vincent',
            'Ssekanjako Shadrak', 'Kyagaba Ibrahim', 'Walakira Gerald', 'Lule Steven',
            'Jumba Paul', 'Wansaso Godfrey', 'Kasujja Muzamir', 'Nsubunga Abdnul',
            'Kiggundu Twaha', 'Birigwa Charles', 'Ggolooba Emma', 'Nyanzi',
            'Counsel Dennis', 'Mbaziira Charles', 'Luyombya Kelvin', 'Robina',
            'Busulwa Charles', 'Ntambi Batte', 'Mukatuliki', 'Ben',
        ];

        foreach ($customers as $name) {
            DB::table('sales_customers')->updateOrInsert(
                ['name' => $name],
                ['credit_limit' => 0, 'is_active' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void {}
};
