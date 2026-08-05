<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Settings — anything the farm can change without a developer.
 |
 | Your spec is full of these: reorder levels, password policy, PAYE
 | bands, currency, notification preferences, working hours. Hardcoding
 | them means a code change every time a rate moves.
 |
 | `is_locked` protects values that must not be edited from the UI once
 | transactions exist (currency is the obvious one — changing it after
 | a year of records would silently corrupt every historic figure).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();     // 'finance.currency'
            $table->string('group', 50)->default('general');
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|int|decimal|bool|json|date
            $table->string('label', 160)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_locked')->default(false);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
