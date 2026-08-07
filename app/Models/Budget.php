<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Budget extends Model { protected $fillable=['category','item','unit','quantity','unit_price','period_start','period_end','planned_amount','notes','status','submitted_at','recorded_by']; protected function casts(): array { return ['period_start'=>'date','period_end'=>'date','submitted_at'=>'datetime','quantity'=>'decimal:2','unit_price'=>'decimal:2','planned_amount'=>'decimal:2']; } public function approvals(): \Illuminate\Database\Eloquent\Relations\MorphMany { return $this->morphMany(Approval::class, 'approvable'); } }
