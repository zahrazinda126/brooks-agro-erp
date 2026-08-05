<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Birds slaughtered for the farm's own use.
 |
 | This gets its own table rather than a column on mortality, and the
 | reason matters:
 |
 | MORTALITY IS A HEALTH FIGURE. Managers act on it. If twenty birds are
 | eaten at a staff function and that shows as a 2% mortality spike,
 | somebody starts investigating a disease that never existed — and the
 | next real spike gets ignored because "that is just the kitchen again".
 |
 | Nor is it a sale. No money comes in, so putting it through sales would
 | overstate revenue and understate margin.
 |
 | It is its own thing: a bird that left the flock, produced no cash, and
 | saved the farm the cost of buying meat.
 |
 | AND IT NEEDS AUTHORISING. "Eaten on the farm" is the easiest cover there
 | is for a bird walking out of the gate. So every record names who
 | authorised it, and a worker cannot enter one alone — it is recorded by a
 | supervisor or manager, and confirmed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bird_consumptions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('animal_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();

            $table->date('consumed_on');
            $table->unsignedInteger('quantity');

            // staff_meals | management | visitors | event | sick_bird | other
            $table->string('purpose', 30)->default('staff_meals');
            $table->text('detail')->nullable();

            /*
             | What the birds would have fetched. Not revenue — it never
             | arrived — but the farm gave up that money, and a year of
             | unvalued consumption is a hole in the accounts nobody can
             | see.
             */
            $table->decimal('estimated_value', 14, 2)->default(0);

            /*
             | Who said yes. Named explicitly rather than assumed from
             | whoever typed it, because the authorisation IS the control.
             */
            $table->foreignId('authorised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authorised_name', 160)->nullable();

            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('recorded_at')->useCurrent();

            // submitted | confirmed | queried
            $table->string('status', 20)->default('submitted');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->text('query_note')->nullable();

            $table->timestamps();

            $table->index(['animal_group_id', 'consumed_on']);
            $table->index(['department_id', 'consumed_on']);
            $table->index(['status', 'consumed_on']);
        });

        Schema::table('animal_groups', function (Blueprint $table) {
            $table->unsignedInteger('total_consumed')->default(0)->after('total_sold');
        });
    }

    public function down(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->dropColumn('total_consumed');
        });

        Schema::dropIfExists('bird_consumptions');
    }
};
