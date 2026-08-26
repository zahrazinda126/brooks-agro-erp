<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->foreignId('edit_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edit_requested_at')->nullable();
            $table->foreignId('edit_authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('edit_authorized_for')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edit_authorized_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('edit_requested_by');
            $table->dropColumn('edit_requested_at');
            $table->dropConstrainedForeignId('edit_authorized_by');
            $table->dropConstrainedForeignId('edit_authorized_for');
            $table->dropColumn('edit_authorized_until');
        });
    }
};
