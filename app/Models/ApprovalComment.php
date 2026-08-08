<?php
namespace App\Models; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ApprovalComment extends Model {protected $fillable=['approval_id','user_id','comment'];public function user():BelongsTo{return $this->belongsTo(User::class);}public function approval():BelongsTo{return $this->belongsTo(Approval::class);}}
