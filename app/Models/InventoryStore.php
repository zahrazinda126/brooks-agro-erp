<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class InventoryStore extends Model
{
    protected $fillable=['code','name','section','description','is_active'];
    protected function casts():array{return ['is_active'=>'boolean'];}
    public function items():HasMany{return $this->hasMany(InventoryItem::class);}
}
