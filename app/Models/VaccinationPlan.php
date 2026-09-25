<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | The standard programme: what is due at what age.
 |
 | Ages are in DAYS rather than weeks, because the early programme is
 | day-precise — Gumboro at 14 days is not the same as Gumboro at 21, and
 | rounding to weeks loses that.
 */
class VaccinationPlan extends Model
{
    protected $fillable = [
        'animal_group_id', 'medication_id', 'breed_id', 'purpose', 'age_days', 'window_days',
        'repeat_every_days', 'repeat_until_days',
        'is_essential', 'note', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_essential' => 'boolean', 'is_active' => 'boolean'];
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function breed(): BelongsTo
    {
        return $this->belongsTo(Breed::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AnimalGroup::class, 'animal_group_id');
    }

    public function scopeFor($query, AnimalGroup $batch)
    {
        return $query
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('animal_group_id')->orWhere('animal_group_id', $batch->id))
            ->where('purpose', $batch->purpose === 'broiler' ? 'broiler' : 'layer')
            ->where(function ($q) use ($batch) {
                $q->whereNull('breed_id')->orWhere('breed_id', $batch->breed_id);
            })
            ->orderBy('age_days');
    }

    /**
     * Every age at which this plan falls due, including repeats.
     *
     * Newcastle through lay is "every ten weeks from day 140 until day
     * 560", which is nine separate due dates. Expanding them here means
     * the rest of the system only ever deals with a plain list.
     */
    public function dueAges(): array
    {
        $ages = [$this->age_days];

        if ($this->repeat_every_days) {
            $until = $this->repeat_until_days ?? ($this->age_days + 365);

            for ($age = $this->age_days + $this->repeat_every_days; $age <= $until; $age += $this->repeat_every_days) {
                $ages[] = $age;
            }
        }

        return $ages;
    }

    public function ageLabel(): string
    {
        if ($this->age_days < 28) {
            return "Day {$this->age_days}";
        }

        return 'Week ' . intdiv($this->age_days, 7);
    }
}
