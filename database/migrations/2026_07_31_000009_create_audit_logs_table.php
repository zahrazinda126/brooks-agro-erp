<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Audit trail — one row per change, for EVERY table in the system.
 |
 | Your org chart lists Audit as a module under Administration, but it
 | can't be built as a module: it has to record changes made inside
 | Poultry, Finance, HR and everywhere else. So it's one generic table
 | that an Eloquent observer writes to automatically. The "Audit"
 | screen is then just a report over this table.
 |
 | old_values / new_values are jsonb — PostgreSQL can index and query
 | inside them, so "show every change to salary" is a real query.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('username', 60)->nullable();   // kept even if user deleted

            $table->string('event', 30);                  // created|updated|deleted|approved|login
            $table->string('auditable_type', 120);        // App\Models\Employee
            $table->unsignedBigInteger('auditable_id')->nullable();

            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            $table->string('module_key', 60)->nullable(); // which screen it happened on
            $table->text('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
