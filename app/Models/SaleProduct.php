<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SaleProduct extends Model {
    protected $fillable=['code','name','category','unit','unit_price','loose_unit_price','stock_quantity','egg_grade_id','is_active','notes'];
    protected function casts():array{return ['unit_price'=>'decimal:2','loose_unit_price'=>'decimal:2','stock_quantity'=>'decimal:2','is_active'=>'boolean'];}
    public function eggGrade(): BelongsTo { return $this->belongsTo(EggGrade::class); }
}
