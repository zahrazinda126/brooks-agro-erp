<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('executive_directives', function (Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable()->after('due_on');
            $table->text('reschedule_reason')->nullable()->after('scheduled_for');
            $table->foreignId('rescheduled_by')->nullable()->after('reschedule_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('rescheduled_at')->nullable()->after('rescheduled_by');
        });
    }

    public function down(): void
    {
        Schema::table('executive_directives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rescheduled_by');
            $table->dropColumn(['scheduled_for','reschedule_reason','rescheduled_at']);
        });
    }
};
