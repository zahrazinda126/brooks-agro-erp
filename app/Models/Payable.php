<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Payable extends Model {
 protected $fillable=['supplier_name','invoice_number','invoice_date','due_date','description','item_name','quantity','unit','amount','amount_paid','status','recorded_by'];
 protected function casts(): array { return ['invoice_date'=>'date','due_date'=>'date','quantity'=>'decimal:2','amount'=>'decimal:2','amount_paid'=>'decimal:2']; }
 public function payments():HasMany{return $this->hasMany(PayablePayment::class);}
 public function lines():HasMany{return $this->hasMany(PayableLine::class);}
}
