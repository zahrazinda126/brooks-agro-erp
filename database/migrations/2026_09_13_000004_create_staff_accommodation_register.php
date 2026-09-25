<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('staff_hostels', function (Blueprint $t) {
            $t->id(); $t->string('name',120)->unique(); $t->string('location',160)->nullable(); $t->text('notes')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('staff_hostel_rooms', function (Blueprint $t) {
            $t->id(); $t->foreignId('staff_hostel_id')->constrained()->cascadeOnDelete(); $t->string('room_number',40); $t->unsignedSmallInteger('bed_spaces')->default(1); $t->string('condition',30)->default('good'); $t->text('notes')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps(); $t->unique(['staff_hostel_id','room_number']);
        });
        Schema::create('staff_accommodation_allocations', function (Blueprint $t) {
            $t->id(); $t->foreignId('staff_hostel_room_id')->constrained()->restrictOnDelete(); $t->foreignId('employee_id')->constrained()->restrictOnDelete(); $t->date('checked_in_on'); $t->date('expected_checkout_on')->nullable(); $t->date('checked_out_on')->nullable(); $t->string('checkout_reason',160)->nullable(); $t->text('notes')->nullable(); $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $t->timestamps(); $t->index(['staff_hostel_room_id','checked_out_on']);
        });
        Schema::create('staff_accommodation_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('staff_hostel_room_id')->nullable()->constrained()->nullOnDelete(); $t->foreignId('employee_id')->nullable()->constrained()->nullOnDelete(); $t->string('item_name',120); $t->decimal('quantity',12,2); $t->boolean('is_returnable')->default(true); $t->date('issued_on'); $t->date('returned_on')->nullable(); $t->string('condition_on_return',60)->nullable(); $t->text('notes')->nullable(); $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('staff_accommodation_items'); Schema::dropIfExists('staff_accommodation_allocations'); Schema::dropIfExists('staff_hostel_rooms'); Schema::dropIfExists('staff_hostels'); }
};
