<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany,MorphMany};
class PayrollRun extends Model { protected $fillable=['payroll_number','frequency','period_start','period_end','pay_date','status','gross_total','deduction_total','net_total','employer_contribution_total','notes','prepared_by','paid_by','paid_at']; protected function casts():array{return ['period_start'=>'date','period_end'=>'date','pay_date'=>'date','paid_at'=>'datetime','gross_total'=>'decimal:2','deduction_total'=>'decimal:2','net_total'=>'decimal:2','employer_contribution_total'=>'decimal:2'];} public function lines():HasMany{return $this->hasMany(PayrollLine::class);} public function approvals():MorphMany{return $this->morphMany(Approval::class,'approvable');} }
