<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class FarmTask extends Model {protected $fillable=['supervisor_assignment_id','worker_id','unit_code','task_date','title','instructions','status','completion_note','submitted_at','reviewed_by','reviewed_at','review_note'];protected function casts():array{return ['task_date'=>'date','submitted_at'=>'datetime','reviewed_at'=>'datetime'];}public function assignment():BelongsTo{return $this->belongsTo(SupervisorAssignment::class,'supervisor_assignment_id');}public function worker():BelongsTo{return $this->belongsTo(User::class,'worker_id');}public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by');}}
