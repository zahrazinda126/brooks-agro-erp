<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class SupervisorAssignment extends Model {protected $fillable=['supervisor_id','unit_code','starts_on','ends_on','shift','notes','is_active','assigned_by'];protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','is_active'=>'boolean'];}public function supervisor():BelongsTo{return $this->belongsTo(User::class,'supervisor_id');}public function assigner():BelongsTo{return $this->belongsTo(User::class,'assigned_by');}public function tasks():HasMany{return $this->hasMany(FarmTask::class);} }
