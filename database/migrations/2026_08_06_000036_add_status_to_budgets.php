<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void {Schema::table('budgets',function(Blueprint $t){$t->string('status',20)->default('draft')->after('notes');$t->timestamp('submitted_at')->nullable()->after('status');});} public function down():void {Schema::table('budgets',function(Blueprint $t){$t->dropColumn(['status','submitted_at']);});} };
