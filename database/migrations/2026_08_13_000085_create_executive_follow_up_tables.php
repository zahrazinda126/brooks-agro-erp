<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('executive_directives', function (Blueprint $table) {
            $table->id(); $table->string('title',180); $table->text('directive');
            $table->string('responsible_team',100); $table->date('issued_on'); $table->date('due_on')->nullable();
            $table->string('status',30)->default('not_started'); $table->unsignedTinyInteger('progress')->default(0);
            $table->text('latest_feedback')->nullable(); $table->timestamp('feedback_at')->nullable();
            $table->foreignId('directed_by')->constrained('users'); $table->foreignId('tracked_by')->constrained('users'); $table->timestamps();
        });
        Schema::create('executive_meetings', function (Blueprint $table) {
            $table->id(); $table->string('title',180); $table->dateTime('starts_at'); $table->dateTime('ends_at')->nullable();
            $table->string('venue',180)->nullable(); $table->text('participants')->nullable(); $table->text('agenda')->nullable();
            $table->string('status',25)->default('scheduled'); $table->text('outcome')->nullable();
            $table->foreignId('scheduled_for')->constrained('users'); $table->foreignId('scheduled_by')->constrained('users'); $table->timestamps();
        });
        Schema::create('approved_fund_follow_ups', function (Blueprint $table) {
            $table->id(); $table->foreignId('approval_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('approved_amount',16,2)->default(0); $table->decimal('amount_accounted_for',16,2)->default(0);
            $table->string('status',30)->default('awaiting_implementation'); $table->text('intended_purpose');
            $table->text('implementation_result')->nullable(); $table->text('evidence_reference')->nullable(); $table->date('follow_up_due_on')->nullable();
            $table->foreignId('tracked_by')->constrained('users'); $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('approved_fund_follow_ups'); Schema::dropIfExists('executive_meetings'); Schema::dropIfExists('executive_directives'); }
};
