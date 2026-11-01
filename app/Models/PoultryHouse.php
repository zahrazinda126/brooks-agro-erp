<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo,HasMany};
class PoultryHouse extends Model {
    protected $fillable=['house_number','house_type','animal_group_id','bird_quantity','is_active','notes','feed_store_id'];
    protected function casts():array{return ['is_active'=>'boolean'];}
    public function batch():BelongsTo{return $this->belongsTo(AnimalGroup::class,'animal_group_id');}
    public function assignments():HasMany{return $this->hasMany(PoultryHouseAssignment::class);}
    public function activeAssignments():HasMany{return $this->assignments()->whereNull('assigned_until');}
    public function displayNumber():string
    {
        return trim((string) preg_replace('/\s*[-–—]\s*B-\d+\/\d+\s*$/i', '', $this->house_number));
    }
}
