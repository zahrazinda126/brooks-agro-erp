<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BatchOrder extends Model
{
    protected $fillable=['order_number','batch_type','supplier_name','breed','quantity_ordered','order_total','ordered_on','expected_on','status','animal_group_id','notes','recorded_by'];
    protected function casts():array{return ['ordered_on'=>'date','expected_on'=>'date','order_total'=>'decimal:2'];}
    public function payments(){return $this->hasMany(BatchOrderPayment::class);}
    public function batch(){return $this->belongsTo(AnimalGroup::class,'animal_group_id');}
    public function recorder(){return $this->belongsTo(User::class,'recorded_by');}
    public function paidAmount():float{return (float)$this->payments->sum('amount');}
    public function balance():float{return max(0,(float)$this->order_total-$this->paidAmount());}
}
