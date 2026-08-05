<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | One person, several hats.
 |
 | A worker who handles rabbits on Monday and goats on Thursday needs both
 | departments. A supervisor may also be a store keeper. The single
 | role_id / department_id columns could not express that.
 |
 | Those columns stay, holding the PRIMARY posting — what shows on a badge
 | and where rank checks start. The pivots below hold the full set, and
 | access is the union of all of them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('department_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'department_id']);
        });

        // Carry every existing account across, marked primary.
        DB::table('users')->whereNotNull('role_id')->orderBy('id')->each(function ($u) {
            DB::table('role_user')->insertOrIgnore([
                'user_id'    => $u->id,
                'role_id'    => $u->role_id,
                'is_primary' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        DB::table('users')->whereNotNull('department_id')->orderBy('id')->each(function ($u) {
            DB::table('department_user')->insertOrIgnore([
                'user_id'       => $u->id,
                'department_id' => $u->department_id,
                'is_primary'    => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('department_user');
    }
};