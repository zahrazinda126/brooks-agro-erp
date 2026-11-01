<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 | Paid farm tasks (manure collection, spraying …), separate from salary.
 | The Production Manager assigns a task to a worker for a period; while it lasts the
 | worker records what they did from their own account; a supervisor confirms it in
 | Daily Collection Approvals. A task can be linked to a product (manure), so confirmed
 | entries add that product to the Sales inventory. Payment goes through Finance's
 | normal entry point.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('farm_task_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('sale_product_id')->nullable()->constrained('sale_products')->nullOnDelete(); // what the task produces for sale
            $table->string('measure', 40)->nullable();          // e.g. kg, bags — asked when the task produces something
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('farm_task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_task_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();      // the worker (records the work)
            $table->string('unit_code', 20);                                             // poultry, goats, rabbits, dogs, garden
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'starts_on']);
        });

        Schema::create('farm_task_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_task_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('farm_task_type_id')->constrained()->restrictOnDelete();
            $table->string('task_name', 120);
            $table->string('unit_code', 20)->index();
            $table->date('done_on')->index();
            $table->decimal('quantity', 14, 2)->nullable();
            $table->string('measure', 40)->nullable();
            $table->text('details')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('verification_status', 20)->default('submitted')->index();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('verification_note')->nullable();
            $table->timestamps();
        });

        // Starting tasks: manure goes to Sales; spraying does not.
        $product = fn (string $code) => DB::table('sale_products')->where('code', $code)->value('id');
        foreach ([
            ['Poultry manure collection', $product('MANURE-POULTRY-KG'), 'kg'],
            ['Goat manure collection', $product('MANURE-GOAT-KG'), 'kg'],
            ['Rabbit manure collection', $product('MANURE-RABBIT-KG'), 'kg'],
            ['Spraying', null, null],
        ] as [$name, $productId, $measure]) {
            DB::table('farm_task_types')->insert(['name' => $name, 'sale_product_id' => $productId, 'measure' => $measure, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_task_entries');
        Schema::dropIfExists('farm_task_assignments');
        Schema::dropIfExists('farm_task_types');
    }
};
