<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SalesCustomer extends Model { protected $fillable=['name','phone','email','address','tax_number','credit_limit','is_active']; protected function casts():array{return ['credit_limit'=>'decimal:2','is_active'=>'boolean'];} }
