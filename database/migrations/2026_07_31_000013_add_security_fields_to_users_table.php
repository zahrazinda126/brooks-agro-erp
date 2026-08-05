<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | The Security section of your spec: two-factor auth, session tracking
 | and password policies. These are columns on the account, not a
 | separate module — the "Security" screen just reads and writes them.
 |
 | Session tracking itself uses Laravel's own `sessions` table; enable
 | it with:  php artisan session:table  (then set SESSION_DRIVER=database)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Two-factor
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');

            // Password policy
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->timestamp('password_expires_at')->nullable()->after('password_changed_at');

            // Lockout after repeated failures
            $table->unsignedSmallInteger('failed_login_attempts')->default(0)->after('password_expires_at');
            $table->timestamp('locked_until')->nullable()->after('failed_login_attempts');
        });

        // Reuse of recent passwords is blocked by keeping their hashes.
        Schema::create('password_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('password_hash');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_histories');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
                'password_changed_at', 'password_expires_at',
                'failed_login_attempts', 'locked_until',
            ]);
        });
    }
};
