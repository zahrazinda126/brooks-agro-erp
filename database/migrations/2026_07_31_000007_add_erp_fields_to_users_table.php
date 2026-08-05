<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Turns Laravel's stock users table into an ERP login account.
 |
 | - login is by USERNAME, not email (farm staff may have no email)
 | - every account is created by HR/admin; there is no public signup
 | - must_change_password forces a reset on first login
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->unique()->after('id');
            $table->foreignId('employee_id')->nullable()->after('username')
                  ->constrained()->nullOnDelete();
            $table->foreignId('role_id')->nullable()->after('employee_id')
                  ->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->after('role_id')
                  ->constrained()->nullOnDelete();

            $table->boolean('is_active')->default(true)->after('password');
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

            $table->softDeletes();
        });

        // Email is optional for farm staff, so drop the NOT NULL constraint.
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('employee_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'username', 'is_active', 'must_change_password',
                'last_login_at', 'last_login_ip', 'deleted_at',
            ]);
        });
    }
};
