<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payable_lines', function (Blueprint $table) {
            $table->foreignId('breed_id')->nullable()->after('unit')
                ->constrained('breeds')->nullOnDelete();
        });

        // Keep the ISA Brown reference in the live database even where the
        // poultry seeder was not run. The operational targets are editable
        // from Poultry > Breeds and are referenced to the supplied cage guide.
        DB::table('breeds')->updateOrInsert(
            ['code' => 'isa-brown'],
            [
                'name' => 'ISA Brown',
                'species' => 'poultry',
                'purpose' => 'layer',
                'lay_start_week' => 18,
                'peak_week' => 28,
                'lay_end_week' => 80,
                'peak_lay_percent' => 95,
                'mature_weight_g' => 1900,
                'daily_feed_g' => 115,
                'slaughter_week' => null,
                'notes' => 'Commercial brown-egg layer. Reference: ISA Brown EU/CS cage product guide (L6230-1). Default operating targets: point of lay from week 18, peak around week 28, 95% peak rate, mature weight about 1.9 kg and feed allowance about 115 g per bird per day. Review against the guide and farm conditions before changing a batch plan.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('payable_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('breed_id');
        });
    }
};
