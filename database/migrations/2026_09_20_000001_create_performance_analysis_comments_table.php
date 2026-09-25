<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('performance_analysis_comments', function (Blueprint $table) {
            $table->id();
            $table->date('period_from');
            $table->date('period_to');
            $table->text('comment')->nullable();
            $table->foreignId('prepared_by')->constrained('users');
            $table->timestamps();
            $table->unique(['period_from', 'period_to']);
        });
    }

    public function down(): void { Schema::dropIfExists('performance_analysis_comments'); }
};
