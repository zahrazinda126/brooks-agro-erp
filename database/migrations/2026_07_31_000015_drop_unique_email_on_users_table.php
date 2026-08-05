<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Email is not an identity here — username is. Two workers may share a
 | family address, or a supervisor's address may be recorded against
 | several staff who have none of their own. The employee record remains
 | the authoritative place for contact details.
 |
 | Left as a plain indexed column so lookups stay fast.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};