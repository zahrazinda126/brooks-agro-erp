<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PayrollLine extends Model { protected $fillable=['payroll_run_id','employee_id','basic_pay','allowances','overtime_pay','gross_pay','nssf_employee','paye','salary_advance','penalties','other_deductions','total_deductions','net_pay','nssf_employer','payment_method','payment_account','notes']; protected function casts():array{return ['basic_pay'=>'decimal:2','allowances'=>'decimal:2','overtime_pay'=>'decimal:2','gross_pay'=>'decimal:2','nssf_employee'=>'decimal:2','paye'=>'decimal:2','salary_advance'=>'decimal:2','penalties'=>'decimal:2','other_deductions'=>'decimal:2','total_deductions'=>'decimal:2','net_pay'=>'decimal:2','nssf_employer'=>'decimal:2'];} public function employee():BelongsTo{return $this->belongsTo(Employee::class);} public function run():BelongsTo{return $this->belongsTo(PayrollRun::class,'payroll_run_id');} }
