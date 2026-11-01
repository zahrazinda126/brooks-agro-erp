<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | What the farm was missing compared with Brooks Farm Hub (added 25 Oct 2026):
 | - workers report water given, sick birds and problems for their house (checked by the
 |   supervisor in Daily Collection Approvals, like eggs, deaths and feed);
 | - farm registers: house preparation before a new flock, incidents and near misses,
 |   faults and maintenance jobs.
 | Nothing existing is changed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poultry_house_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('animal_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('poultry_house_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_code', 20)->default('poultry');
            $table->string('report_type', 20)->index(); // water | sick_birds | problem
            $table->date('reported_on')->index();
            $table->decimal('quantity', 12, 2)->nullable(); // litres of water, or number of sick birds
            $table->string('category', 40)->nullable(); // for problems
            $table->text('details')->nullable();
            $table->foreignId('recorded_by')->constrained('users');
            $table->string('verification_status', 20)->default('submitted')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
        });

        Schema::create('farm_register_entries', function (Blueprint $table) {
            $table->id();
            $table->string('register', 30)->index(); // house_preparation | incident | fault
            $table->date('entry_date')->index();
            $table->foreignId('poultry_house_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location', 160)->nullable();
            $table->json('answers')->nullable();
            $table->string('severity', 20)->nullable();
            $table->text('details')->nullable();
            $table->text('action_taken')->nullable();
            $table->string('status', 20)->default('open')->index(); // open | resolved | ready | not_ready
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->text('closing_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_register_entries');
        Schema::dropIfExists('poultry_house_reports');
    }
};
