<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('executive_directives', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('tracked_by')->constrained('users')->nullOnDelete();
            $table->foreignId('forwarded_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('forwarded_at')->nullable()->after('forwarded_by');
            $table->timestamp('acknowledged_at')->nullable()->after('forwarded_at');
            $table->timestamp('completed_at')->nullable()->after('acknowledged_at');
        });
        Schema::table('executive_communications', function (Blueprint $table) {
            $table->string('action_url', 500)->nullable()->after('recipient_id');
            $table->timestamp('read_at')->nullable()->after('action_url');
        });
    }

    public function down(): void
    {
        Schema::table('executive_communications', fn (Blueprint $table) => $table->dropColumn(['action_url','read_at']));
        Schema::table('executive_directives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('forwarded_by');
            $table->dropColumn(['forwarded_at','acknowledged_at','completed_at']);
        });
    }
};
