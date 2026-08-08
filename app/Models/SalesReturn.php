<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesReturn extends Model { protected $fillable=['sale_id','sale_line_id','returned_on','quantity','refund_amount','reason','recorded_by']; protected function casts():array{return ['returned_on'=>'date','quantity'=>'decimal:2','refund_amount'=>'decimal:2'];} }
