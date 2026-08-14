<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/*
 | Egg prices with history.
 |
 | Market prices move week to week. A single price column would silently
 | revalue every past sale each time it changed, so each price instead has
 | a period: a sale in March is always worth March's price, whatever the
 | price is today.
 */
class EggPrice extends Model
{
    protected $fillable = ['egg_grade_id', 'price_per_tray',
                           'effective_from', 'effective_to', 'market', 'note', 'set_by'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to'   => 'date',
            'price_per_tray' => 'decimal:2',
        ];
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(EggGrade::class, 'egg_grade_id');
    }

    public function setter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by');
    }

    public function isCurrent(): bool
    {
        return $this->effective_to === null;
    }

    /** The price that applied to a grade on a given date. */
    public static function priceOn(int $gradeId, $date): ?float
    {
        $row = static::where('egg_grade_id', $gradeId)
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $row ? (float) $row->price_per_tray : null;
    }

    /**
     * Set a new price. Closes the previous period the day before rather
     * than overwriting it, so history stays intact.
     */
    public static function change(int $gradeId, float $price, $from, ?int $userId = null, ?string $note = null): self
    {
        $from = \Illuminate\Support\Carbon::parse($from);

        static::where('egg_grade_id', $gradeId)
            ->whereNull('effective_to')
            ->update(['effective_to' => $from->copy()->subDay()->toDateString()]);

        return static::create([
            'egg_grade_id'   => $gradeId,
            'price_per_tray' => $price,
            'effective_from' => $from->toDateString(),
            'effective_to'   => null,
            'set_by'         => $userId,
            'note'           => $note,
        ]);
    }
}
