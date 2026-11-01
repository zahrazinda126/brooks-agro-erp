<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * The CEO's daily report to the Director (replacing her Word document). Poultry production, the week's feed,
 * staffing, other enterprises, the day's sales and outstanding payables come from the system and are frozen
 * in `facts` when it is sent; the CEO writes the highlights, projects and notes.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('ceo_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('report_date');
            $table->text('highlights')->nullable();
            $table->text('projects')->nullable();
            $table->text('hr_notes')->nullable();
            $table->text('enterprise_notes')->nullable();
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
        Schema::dropIfExists('ceo_daily_reports');
    }
};
