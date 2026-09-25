<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number', 80)->unique();
            $table->string('make_model', 160)->nullable();
            $table->string('vehicle_type', 80)->nullable();
            $table->decimal('capacity', 10, 2)->nullable();
            $table->string('default_driver_name', 160)->nullable();
            $table->decimal('current_odometer_km', 12, 1)->nullable();
            $table->decimal('next_service_odometer_km', 12, 1)->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Keep existing fleet-history vehicles usable once the register goes live.
        DB::table('fleet_records')->select('vehicle_id')->whereNotNull('vehicle_id')->distinct()->orderBy('vehicle_id')
            ->each(function (object $record): void {
                $registration = trim((string) $record->vehicle_id);
                if ($registration !== '') {
                    DB::table('company_vehicles')->insertOrIgnore([
                        'registration_number' => $registration,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_vehicles');
    }
};
