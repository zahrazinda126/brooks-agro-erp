<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model;
class PayablePayment extends Model { protected $fillable=['payable_id','paid_date','amount','farm_account','payment_method','reference','recorded_by']; protected function casts():array{return ['paid_date'=>'date','amount'=>'decimal:2'];} }
