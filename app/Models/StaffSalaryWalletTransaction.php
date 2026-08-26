<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class StaffSalaryWalletTransaction extends Model {
 protected $fillable=['employee_id','payroll_run_id','payroll_line_id','type','amount','transaction_date','company_account','payment_method','reference','notes','recorded_by'];
 protected function casts():array{return ['amount'=>'decimal:2','transaction_date'=>'date'];}
 public function employee():BelongsTo{return $this->belongsTo(Employee::class);}
 public function payrollRun():BelongsTo{return $this->belongsTo(PayrollRun::class);}
 public function payrollLine():BelongsTo{return $this->belongsTo(PayrollLine::class);}
 public function recorder():BelongsTo{return $this->belongsTo(User::class,'recorded_by');}
}
