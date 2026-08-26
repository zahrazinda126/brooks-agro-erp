<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SalePriceChangeRequest extends Model {
 protected $fillable=['sale_product_id','previous_unit_price','proposed_unit_price','previous_loose_unit_price','proposed_loose_unit_price','effective_from','request_note','status','requested_by','decided_by','decided_at','decision_note'];
 protected function casts():array{return ['effective_from'=>'date','decided_at'=>'datetime','previous_unit_price'=>'decimal:2','proposed_unit_price'=>'decimal:2','previous_loose_unit_price'=>'decimal:2','proposed_loose_unit_price'=>'decimal:2'];}
 public function product():BelongsTo{return $this->belongsTo(SaleProduct::class,'sale_product_id');}
 public function requester():BelongsTo{return $this->belongsTo(User::class,'requested_by');}
 public function decisionMaker():BelongsTo{return $this->belongsTo(User::class,'decided_by');}
}
