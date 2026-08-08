<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class Sale extends Model { protected $fillable=['invoice_number','sale_date','customer_id','customer_name','payment_type','payment_method','farm_account','subtotal','discount','total','amount_paid','balance','status','notes','sold_by']; protected function casts():array{return ['sale_date'=>'date','subtotal'=>'decimal:2','discount'=>'decimal:2','total'=>'decimal:2','amount_paid'=>'decimal:2','balance'=>'decimal:2'];} public function customer():BelongsTo{return $this->belongsTo(SalesCustomer::class,'customer_id');} public function lines():HasMany{return $this->hasMany(SaleLine::class);} public function payments():HasMany{return $this->hasMany(SalesPayment::class);} }
