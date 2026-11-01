<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Sick animals taken into a sick bay on one day, and what became of them. */
class SickBayAdmission extends Model
{
    public const SIGNS = ['Coughing / sneezing', 'Diarrhoea', 'Limping / injury', 'Not eating', 'Pecked / wounds', 'Weak / droopy', 'Swollen eyes or face', 'Other'];

    protected $fillable = ['sick_bay_id', 'unit_code', 'animal_group_id', 'poultry_house_id', 'livestock_group_id', 'quantity', 'in_care', 'recovered', 'died', 'culled', 'admitted_on', 'signs', 'condition', 'notes', 'status', 'discharged_on', 'admitted_by'];

    protected function casts(): array
    {
        return ['admitted_on' => 'date', 'discharged_on' => 'date', 'signs' => 'array'];
    }

    public function sickBay(): BelongsTo { return $this->belongsTo(SickBay::class); }
    public function batch(): BelongsTo { return $this->belongsTo(AnimalGroup::class, 'animal_group_id'); }
    public function house(): BelongsTo { return $this->belongsTo(PoultryHouse::class, 'poultry_house_id'); }
    public function admittedBy(): BelongsTo { return $this->belongsTo(User::class, 'admitted_by'); }
    public function checks(): HasMany { return $this->hasMany(SickBayCheck::class)->orderBy('checked_on')->orderBy('id'); }

    /** The batch / group and house the animals came from, for lists and reports. */
    public function fromLabel(): string
    {
        if ($this->unit_code === 'poultry') {
            return trim(($this->batch?->code ?? 'Batch').' · '.($this->house ? $this->house->displayNumber() : ''), ' ·');
        }
        return (string) (\Illuminate\Support\Facades\DB::table('livestock_groups')->where('id', $this->livestock_group_id)->value('group_code') ?? 'Group');
    }

    public function daysInCare(): int
    {
        return (int) $this->admitted_on->diffInDays($this->discharged_on ?? today());
    }
}
