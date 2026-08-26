<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DailyManagementReportSection extends Model{
 protected $fillable=['report_date','department_code','section_code','status','summary','activities','challenges','recommendations','details','prepared_by','shared_at','reviewed_by','reviewed_at','review_note'];
 protected function casts():array{return ['report_date'=>'date','details'=>'array','shared_at'=>'datetime','reviewed_at'=>'datetime'];}
 public function preparer():BelongsTo{return $this->belongsTo(User::class,'prepared_by');}
 public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by');}
}
