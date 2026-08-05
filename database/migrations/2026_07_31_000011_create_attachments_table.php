<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Attachments — files bolted onto any record in the system.
 |
 | Your spec asks for photo evidence on completed tasks, but the same
 | need shows up as vet reports on a mortality record, delivery notes
 | on a goods-received entry, supplier quotations, signed contracts and
 | scanned receipts. One polymorphic table serves all of them.
 |
 | `disk` is stored per row so the local -> cloud migration later is a
 | per-file decision, not a big-bang move.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->string('attachable_type', 120);
            $table->unsignedBigInteger('attachable_id');

            $table->string('collection', 40)->default('default'); // 'evidence', 'vet-report'
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('checksum', 64)->nullable();   // detects duplicate uploads

            // Where a phone photo was actually taken — useful when a worker
            // submits evidence for a house they were never in.
            $table->decimal('captured_lat', 10, 7)->nullable();
            $table->decimal('captured_lng', 10, 7)->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('collection');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
