<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_superuser', 'rank', 'is_active'];

    protected function casts(): array
    {
        return ['is_superuser' => 'boolean', 'is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class)
            ->withPivot(['can_view', 'can_create', 'can_edit', 'can_delete', 'can_approve'])
            ->withTimestamps();
    }
}
