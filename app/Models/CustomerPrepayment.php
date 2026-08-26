<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class CustomerPrepayment extends Model
{
    protected $fillable=['customer_id','customer_name','received_date','amount','amount_used','farm_account','payment_method','reference','recorded_by'];
    protected function casts(): array{return ['received_date'=>'date','amount'=>'decimal:2','amount_used'=>'decimal:2'];}
    public function customer(){return $this->belongsTo(SalesCustomer::class,'customer_id');}
}
