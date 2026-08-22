<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_events', function (Blueprint $table) {
            $table->string('application_route', 80)->nullable()->after('dose_given');
            $table->string('vaccine_batch_number', 100)->nullable()->after('application_route');
            $table->string('manufacturer', 160)->nullable()->after('vaccine_batch_number');
            $table->date('manufactured_on')->nullable()->after('manufacturer');
            $table->date('expires_on')->nullable()->after('manufactured_on');
            $table->date('next_due_on')->nullable()->after('expires_on')->index();
        });
    }

    public function down(): void
    {
        Schema::table('health_events', function (Blueprint $table) {
            $table->dropIndex(['next_due_on']);
            $table->dropColumn(['application_route', 'vaccine_batch_number', 'manufacturer', 'manufactured_on', 'expires_on', 'next_due_on']);
        });
    }
};
