<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DailyManagementReportComment extends Model{
 protected $fillable=['report_date','department_code','unit_report_id','comment_type','body','author_id'];
 protected function casts():array{return ['report_date'=>'date'];}
 public function author():BelongsTo{return $this->belongsTo(User::class,'author_id');}
 public function unitReport():BelongsTo{return $this->belongsTo(DailyManagementReportSection::class,'unit_report_id');}
}
