<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ExecutiveMeeting extends Model { protected $fillable=['title','starts_at','ends_at','venue','participants','agenda','status','outcome','scheduled_for','scheduled_by']; protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime'];} }
