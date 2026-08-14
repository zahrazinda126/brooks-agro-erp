<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budgets', function (Blueprint $table) {
            $table->string('funding_status', 20)->default('pending')->after('status');
            $table->decimal('funded_amount', 16, 2)->nullable()->after('funding_status');
            $table->text('funding_note')->nullable()->after('funded_amount');
            $table->foreignId('funding_decided_by')->nullable()->after('funding_note')->constrained('users')->nullOnDelete();
            $table->timestamp('funding_decided_at')->nullable()->after('funding_decided_by');
            $table->index(['period_start', 'funding_status']);
        });

        Schema::create('budget_funding_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 20);
            $table->decimal('funded_amount', 16, 2);
            $table->text('note')->nullable();
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['budget_id', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_funding_decisions');
        Schema::table('budgets', function (Blueprint $table) {
            $table->dropIndex(['period_start', 'funding_status']);
            $table->dropConstrainedForeignId('funding_decided_by');
            $table->dropColumn(['funding_status', 'funded_amount', 'funding_note', 'funding_decided_at']);
        });
    }
};
