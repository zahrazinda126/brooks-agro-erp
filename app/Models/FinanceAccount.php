<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A chart-of-accounts entry (codes and hierarchy follow the finance workbook). */
class FinanceAccount extends Model
{
    protected $fillable = ['code', 'name', 'workbook_label', 'type', 'statement', 'parent_id', 'is_group', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_group' => 'boolean', 'is_active' => 'boolean'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FinancialTransaction::class);
    }
}
