<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesPayment extends Model { protected $fillable=['sale_id','paid_on','amount','payment_method','farm_account','reference','recorded_by']; protected function casts():array{return ['paid_on'=>'date','amount'=>'decimal:2'];} }
