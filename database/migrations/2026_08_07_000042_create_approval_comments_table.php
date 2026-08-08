<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void {Schema::create('approval_comments',function(Blueprint $t){$t->id();$t->foreignId('approval_id')->constrained()->cascadeOnDelete();$t->foreignId('user_id')->constrained()->cascadeOnDelete();$t->text('comment');$t->timestamps();$t->index(['approval_id','created_at']);});} public function down():void{Schema::dropIfExists('approval_comments');} };
