<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExecutiveDirective extends Model { protected $fillable=['title','directive','responsible_team','issued_on','due_on','status','progress','latest_feedback','feedback_at','directed_by','tracked_by']; protected function casts():array{return ['issued_on'=>'date','due_on'=>'date','feedback_at'=>'datetime'];} }
