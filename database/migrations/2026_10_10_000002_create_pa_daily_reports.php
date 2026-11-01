<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The PA's daily report to the Director (instead of a Word document). The day's facts (visitors, directives,
 * meetings, approved money, construction) come from the system and are frozen in `facts` when it is submitted;
 * the PA only writes what was done, issues for the Director, and tomorrow's plan.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('pa_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->text('did_today')->nullable();
            $table->text('issues')->nullable();
            $table->text('plan_tomorrow')->nullable();
            $table->string('status', 20)->default('draft'); // draft, submitted
            $table->timestamp('submitted_at')->nullable();
            $table->json('facts')->nullable();
            $table->text('director_comment')->nullable();
            $table->foreignId('commented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('commented_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pa_daily_reports');
    }
};
