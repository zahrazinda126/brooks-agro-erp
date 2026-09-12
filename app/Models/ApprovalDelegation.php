<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ApprovalDelegation extends Model
{
    protected $fillable=['delegator_id','delegate_id','role_code','starts_on','ends_on','reason','is_active'];
    protected function casts():array{return ['starts_on'=>'date','ends_on'=>'date','is_active'=>'boolean'];}
    public function delegator(){return $this->belongsTo(User::class,'delegator_id');}
    public function delegate(){return $this->belongsTo(User::class,'delegate_id');}
    public function scopeCurrent($query){return $query->where('is_active',true)->whereDate('starts_on','<=',today())->whereDate('ends_on','>=',today());}
}
