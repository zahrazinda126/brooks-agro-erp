<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ApprovalStep extends Model
{
    protected $fillable=['approval_id','stage','role_code','status','decided_by','decided_at','decision_note'];
    protected function casts(): array { return ['decided_at'=>'datetime']; }
    public function approval(): BelongsTo { return $this->belongsTo(Approval::class); }
    public function decisionMaker(): BelongsTo { return $this->belongsTo(User::class,'decided_by'); }
}
