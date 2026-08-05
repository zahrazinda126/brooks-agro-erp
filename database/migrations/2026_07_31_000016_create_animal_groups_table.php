<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Animal groups — batches and litters.
 |
 | Poultry has no individual identity: a batch is "B-03, 3,200 birds",
 | and when two die the count becomes 3,198 without anyone knowing which
 | two. Rabbits use this for litters while the does and bucks themselves
 | live in `animals`.
 |
 | This is also the COSTING UNIT. Chicks, feed, medicine, vaccination and
 | labour attach to a batch; eggs, culls and sales credit it. It is the
 | only way to answer "did B-03 make money".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('animal_groups', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();        // B-03
            $table->string('name', 120)->nullable();     // "House 2 layers"

            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('breed_id')->nullable()->constrained()->nullOnDelete();

            // layer | broiler | dual | kienyeji | litter
            $table->string('purpose', 20)->default('layer');

            /* --- Arrival --------------------------------------------- */
            $table->date('received_date');
            // Chicks normally arrive at day old, but point-of-lay pullets
            // are sometimes bought in. Age at arrival keeps the maths right.
            $table->unsignedSmallInteger('age_days_on_arrival')->default(0);
            $table->unsignedInteger('quantity_received');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->default(0);
            $table->foreignId('supplier_id')->nullable();   // suppliers table comes later
            $table->string('supplier_name', 160)->nullable();
            $table->string('invoice_no', 60)->nullable();

            /* --- Running state --------------------------------------- */
            // Maintained as mortality, culls and sales are recorded, so
            // "how many birds are alive" never needs a scan of every row.
            $table->unsignedInteger('current_quantity')->default(0);
            $table->unsignedInteger('total_mortality')->default(0);
            $table->unsignedInteger('total_culled')->default(0);
            $table->unsignedInteger('total_sold')->default(0);

            /* --- Laying ---------------------------------------------- */
            // Set when the first egg is recorded. The expected date comes
            // from the breed; the actual one tells you whether the flock
            // is early, late or in trouble.
            $table->date('expected_lay_date')->nullable();
            $table->date('actual_lay_date')->nullable();

            $table->string('house', 60)->nullable();
            $table->string('status', 20)->default('active');  // active|closed|sold
            $table->date('closed_date')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['department_id', 'status']);
            $table->index('received_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('animal_groups');
    }
};
