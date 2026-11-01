<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Goat, rabbit and dog deaths now go through a manager's examination, like poultry:
 | the recorder gives a suspected cause, the manager records the established cause,
 | the action taken and whether a vet was called. Deaths recorded before this change
 | are treated as already examined.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('livestock_mortality_records', function (Blueprint $table) {
            $table->string('status', 30)->default('pending_assessment')->after('notes')->index();
            $table->string('established_cause', 160)->nullable()->after('status');
            $table->text('assessment_note')->nullable()->after('established_cause');
            $table->text('action_taken')->nullable()->after('assessment_note');
            $table->boolean('vet_called')->default(false)->after('action_taken');
            $table->string('vet_name', 160)->nullable()->after('vet_called');
            $table->foreignId('assessed_by')->nullable()->after('vet_name')->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable()->after('assessed_by');
        });

        DB::table('livestock_mortality_records')->update(['status' => 'assessed', 'established_cause' => DB::raw('suspected_cause')]);
    }

    public function down(): void
    {
        Schema::table('livestock_mortality_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessed_by');
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'established_cause', 'assessment_note', 'action_taken', 'vet_called', 'vet_name', 'assessed_at']);
        });
    }
};
