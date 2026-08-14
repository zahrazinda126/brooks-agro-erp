<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ApprovedFundFollowUp extends Model { protected $fillable=['approval_id','approved_amount','amount_accounted_for','status','intended_purpose','implementation_result','evidence_reference','follow_up_due_on','tracked_by']; protected function casts():array{return ['approved_amount'=>'decimal:2','amount_accounted_for'=>'decimal:2','follow_up_due_on'=>'date'];} public function approval():BelongsTo{return $this->belongsTo(Approval::class);} }
