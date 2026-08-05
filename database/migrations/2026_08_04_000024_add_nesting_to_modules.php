<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Modules can now sit inside other modules.
 |
 | The sidebar has one level: a group heading, then a flat list. That was
 | fine at a dozen screens. Give the Finance Manager their full remit and
 | the Finance group alone becomes twenty-five items — a wall of text where
 | Cash Book, Chart of Accounts and Customer Ledger all look equally
 | important and none is findable.
 |
 | A second level fixes it, and it is a data change rather than a code one:
 | a module names its parent, and the sidebar renders whatever shape the
 | data describes.
 |
 | `parent_key` rather than parent_id, because the seeders work in keys and
 | a key survives a database rebuild.
 |
 | `is_container` marks a heading that is not itself a screen — Money In,
 | Money Out. It has no route and cannot be opened; it only holds things.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('parent_key', 60)->nullable()->after('group');
            $table->boolean('is_container')->default(false)->after('parent_key');

            $table->index('parent_key');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropIndex(['parent_key']);
            $table->dropColumn(['parent_key', 'is_container']);
        });
    }
};
