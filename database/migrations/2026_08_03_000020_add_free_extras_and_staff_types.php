<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Two additions.
 |
 | 1. FREE EXTRAS ON A BATCH
 |
 | Suppliers throw in extras to cover early losses — order 1,000 chicks and
 | 1,020 arrive, with 1,000 on the invoice. Recording only the invoice
 | figure makes the bird count wrong from day one, and recording all 1,020
 | as paid makes the cost per bird wrong instead.
 |
 | So they are counted separately. Birds received = paid + free; total cost
 | comes from the paid figure alone; cost per bird then spreads the real
 | money over the real birds, which is the honest number.
 |
 | 2. TEMPORARY STAFF
 |
 | Builders, interns and holiday workers need records — they are paid, they
 | do work, and somebody must be able to say who was on the farm in March.
 | What they must NOT do is consume numbers from the permanent series, or
 | share one.
 |
 | Staff numbers are never reused. If BAF-0007 ever means two people, every
 | payroll line, task and record against that number becomes ambiguous, and
 | nobody finds out until a payment is queried two years later. Separate
 | series keep them apart for good.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            // Extras given free by the supplier, on top of what was invoiced.
            $table->unsignedInteger('quantity_free')->default(0)->after('quantity_received');
        });

        Schema::table('employees', function (Blueprint $table) {
            /*
             | Expected end date for anyone temporary. Lets HR see who is
             | about to leave rather than discovering it on payday, and
             | drives a reminder later.
             */
            $table->date('expected_end_date')->nullable()->after('hire_date');

            // Which school, college or programme an intern belongs to.
            $table->string('placement_from', 160)->nullable()->after('expected_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('animal_groups', function (Blueprint $table) {
            $table->dropColumn('quantity_free');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['expected_end_date', 'placement_from']);
        });
    }
};
