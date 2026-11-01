<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Payable extends Model {
 protected $fillable=['construction_project_id','supplier_name','invoice_number','invoice_date','due_date','description','item_name','quantity','unit','amount','amount_paid','status','recorded_by','variance_approved_by','variance_approved_at','variance_note'];
 protected function casts(): array { return ['invoice_date'=>'date','due_date'=>'date','quantity'=>'decimal:2','amount'=>'decimal:2','amount_paid'=>'decimal:2','variance_approved_at'=>'datetime']; }
 public function payments():HasMany{return $this->hasMany(PayablePayment::class);}
 public function lines():HasMany{return $this->hasMany(PayableLine::class);}
 /** Feed deliveries this bill charges for (feed_delivery_verifications ids). */
 public function feedDeliveryIds(): \Illuminate\Support\Collection { return \Illuminate\Support\Facades\DB::table('payable_feed_delivery')->where('payable_id',$this->id)->pluck('feed_delivery_verification_id'); }
 public function varianceApprover(){return $this->belongsTo(User::class,'variance_approved_by');}
}
