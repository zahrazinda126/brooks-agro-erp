<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BatchOrderPayment extends Model
{
    protected $fillable=['batch_order_id','paid_on','amount','payment_method','farm_account','reference','recorded_by'];
    protected function casts():array{return ['paid_on'=>'date','amount'=>'decimal:2'];}
    public function order(){return $this->belongsTo(BatchOrder::class,'batch_order_id');}
    public function recorder(){return $this->belongsTo(User::class,'recorded_by');}
}
