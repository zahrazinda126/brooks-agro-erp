<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PoultryHouseAssignment extends Model {
    protected $fillable=['poultry_house_id','employee_id','assigned_from','assigned_until','assigned_by'];
    protected function casts():array{return ['assigned_from'=>'date','assigned_until'=>'date'];}
    public function house():BelongsTo{return $this->belongsTo(PoultryHouse::class,'poultry_house_id');}
    public function employee():BelongsTo{return $this->belongsTo(Employee::class);}
}
