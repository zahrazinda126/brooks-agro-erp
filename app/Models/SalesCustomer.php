<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesCustomer extends Model
{
    protected $fillable = ['name', 'phone', 'email', 'address', 'tax_number', 'credit_limit', 'is_active'];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function prepayments(): HasMany
    {
        return $this->hasMany(CustomerPrepayment::class, 'customer_id');
    }
}
