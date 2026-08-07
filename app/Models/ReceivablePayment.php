<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ReceivablePayment extends Model { protected $fillable=['receivable_id','received_date','amount','farm_account','payment_method','reference','source','recorded_by']; protected function casts(): array{return ['received_date'=>'date','amount'=>'decimal:2'];} }
