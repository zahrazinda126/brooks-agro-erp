<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Payable extends Model { protected $fillable=['supplier_name','invoice_number','invoice_date','due_date','description','amount','amount_paid','status','recorded_by']; protected function casts(): array { return ['invoice_date'=>'date','due_date'=>'date','amount'=>'decimal:2','amount_paid'=>'decimal:2']; } }
