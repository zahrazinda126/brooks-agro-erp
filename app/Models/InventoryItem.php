<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $fillable=['code','name','category','unit','reorder_level','default_location','notes','is_active','created_by'];
    protected function casts():array{return ['reorder_level'=>'decimal:2','is_active'=>'boolean'];}
    public function movements():HasMany{return $this->hasMany(InventoryMovement::class);}
}
