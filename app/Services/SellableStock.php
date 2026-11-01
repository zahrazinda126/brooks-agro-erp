<?php

namespace App\Services;

use App\Models\AnimalGroup;
use App\Models\SaleProduct;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What the farm has that can be sold, worked out from the records (nobody types stock in):
 *   eggs   = approved collections − sales + returns, per grade
 *   birds  = batch intake − deaths/culls − birds used on the farm − sales, per batch
 *   goats, rabbits, dogs = group intake + births − deaths, per group
 *   garden = approved harvests − losses − sales, per crop
 * Every change is an event (date, item, added / sold / lost), so stock can be shown for any day.
 */
class SellableStock
{
    public const UNITS = ['poultry' => 'Poultry', 'goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs', 'garden' => 'Garden'];

    /** Egg conditions that are recorded at collection but never sold. */
    private const NON_SALE_EGGS = ['Broken eggs', 'Dirty eggs', 'Soft shell eggs'];

    private ?Collection $items = null;
    private ?Collection $events = null;

    /** Stock at the end of $on, one row per item. */
    public function onHand(Carbon $on, array $filters = []): Collection
    {
        $day = $on->toDateString();
        $totals = $this->events()->filter(fn ($e) => $e['date'] <= $day)->groupBy('key')
            ->map(fn ($events) => $events->sum(fn ($e) => $e['kind'] === 'added' ? $e['qty'] : -$e['qty']));

        // Only items with something recorded by that day: a product appears once it is first recorded.
        return $this->filterItems($filters)
            ->filter(fn ($item) => $totals->has($item['key']))
            ->map(fn ($item) => $item + ['quantity' => max(0.0, round((float) $totals[$item['key']], 2))])
            ->when(! empty($filters['hide_empty']), fn ($rows) => $rows->filter(fn ($row) => $row['quantity'] > 0))
            ->map(fn ($row) => $row + ['shown' => $this->describe($row, $row['quantity'])])
            ->values();
    }

    /** Day-by-day movement between $from and $to: opening, added, sold, lost, closing per item and day. */
    public function movements(Carbon $from, Carbon $to, array $filters = []): Collection
    {
        $items = $this->filterItems($filters)->keyBy('key');
        $start = $from->toDateString();
        $end = $to->toDateString();
        $rows = collect();

        foreach ($this->events()->whereIn('key', $items->keys())->groupBy('key') as $key => $events) {
            $running = $events->filter(fn ($e) => $e['date'] < $start)->sum(fn ($e) => $e['kind'] === 'added' ? $e['qty'] : -$e['qty']);
            foreach ($events->filter(fn ($e) => $e['date'] >= $start && $e['date'] <= $end)->groupBy('date')->sortKeys() as $date => $day) {
                $added = $day->where('kind', 'added')->sum('qty');
                $sold = $day->where('kind', 'sold')->sum('qty');
                $lost = $day->where('kind', 'lost')->sum('qty');
                $rows->push($items[$key] + [
                    'date' => $date, 'opening' => $running, 'added' => $added, 'sold' => $sold, 'lost' => $lost,
                    'closing' => $running + $added - $sold - $lost,
                    'added_from' => $day->where('kind', 'added')->pluck('source')->unique()->join(', '),
                    'lost_to' => $day->where('kind', 'lost')->pluck('source')->unique()->join(', '),
                ]);
                $running += $added - $sold - $lost;
            }
        }

        return $rows->sortBy([['date', 'desc'], ['unit', 'asc'], ['item', 'asc']])->values();
    }

    /**
     * What is in stock now for each sale product, as text for the sale form ("40 trays + 12 loose", "85 kg").
     * Null when the product's stock is not tracked yet.
     */
    public function forProducts(Collection $products): array
    {
        $rows = $this->onHand(now());
        $byKey = $rows->keyBy('key');
        $out = [];
        foreach ($products as $product) {
            $name = mb_strtolower(trim($product->name));
            $row = $product->egg_grade_id ? $byKey->get('egg-'.$product->egg_grade_id) : $byKey->get('product-'.$product->id);
            if (! $row && $product->category === 'garden') $row = $rows->first(fn ($r) => $r['unit'] === 'garden' && mb_strtolower($r['item']) === $name);
            if ($row) { $out[$product->id] = $row['shown']; continue; }
            // Tracked but nothing recorded yet (eggs, garden produce, task products such as manure).
            if ($product->egg_grade_id || $product->category === 'garden' || $this->items()->contains('key', 'product-'.$product->id)) { $out[$product->id] = 'Nothing in stock'; continue; }
            // Live animals and birds: what the groups / batches hold in total.
            if ($product->unit === 'animal' && isset(self::UNITS[$product->category])) {
                $out[$product->id] = $this->describe(['measure' => 'animals'], (float) $rows->where('unit', $product->category)->where('measure', 'animals')->sum('quantity'));
            } elseif ($product->unit === 'bird') {
                $out[$product->id] = $this->describe(['measure' => 'birds'], (float) $rows->where('measure', 'birds')->sum('quantity'));
            } else {
                $out[$product->id] = null;
            }
        }
        return $out;
    }

    /** "40 trays + 12 loose" for eggs, otherwise the quantity and its measure. */
    public function describe(array $item, float $quantity): string
    {
        if ($item['per_tray'] ?? null) {
            $eggs = (int) floor($quantity);
            return number_format(intdiv($eggs, $item['per_tray'])).' trays + '.number_format($eggs % $item['per_tray']).' loose';
        }
        return number_format($quantity, fmod($quantity, 1) ? 2 : 0).' '.$item['measure'];
    }

    private function filterItems(array $filters): Collection
    {
        $unit = $filters['unit'] ?? null;
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));

        return $this->items()
            ->when($unit && isset(self::UNITS[$unit]), fn ($items) => $items->where('unit', $unit))
            ->when($q !== '', fn ($items) => $items->filter(fn ($i) => str_contains(mb_strtolower($i['item'].' '.$i['detail']), $q)))
            ->values();
    }

    private function items(): Collection
    {
        if (! $this->items) $this->load();
        return $this->items;
    }

    private function events(): Collection
    {
        if (! $this->events) $this->load();
        return $this->events;
    }

    private function load(): void
    {
        $items = collect();
        $events = collect();
        $add = function (string $key, string $date, string $kind, float $qty, string $source) use ($events) {
            if ($qty != 0) $events->push(['key' => $key, 'date' => substr($date, 0, 10), 'kind' => $kind, 'qty' => $qty, 'source' => $source]);
        };

        // Eggs, per grade.
        $eggProducts = SaleProduct::with('eggGrade')->whereNotNull('egg_grade_id')->where('is_active', true)
            ->whereNotIn('name', self::NON_SALE_EGGS)->orderBy('name')->get()->unique('egg_grade_id');
        foreach ($eggProducts as $product) {
            $key = 'egg-'.$product->egg_grade_id;
            $perTray = max(1, (int) ($product->eggGrade?->eggs_per_tray ?? 30));
            $items->push(['key' => $key, 'unit' => 'poultry', 'item' => $product->name, 'detail' => 'Eggs · '.$perTray.' per tray', 'measure' => 'eggs', 'per_tray' => $perTray]);
            DB::table('egg_collection_lines')->join('egg_collections', 'egg_collections.id', '=', 'egg_collection_lines.egg_collection_id')
                ->where('egg_collections.status', 'approved')->where('egg_collection_lines.egg_grade_id', $product->egg_grade_id)
                ->get(['egg_collections.collected_on', 'egg_collection_lines.total_eggs'])
                ->each(fn ($r) => $add($key, (string) $r->collected_on, 'added', (float) $r->total_eggs, 'collected'));
            $inEggs = fn ($unit, $qty) => (float) $qty * ($unit === 'egg' ? 1 : $perTray);
            DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.product_id', $product->id)->where('sales.status', '!=', 'void')
                ->get(['sales.sale_date', 'sale_lines.unit', 'sale_lines.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->sale_date, 'sold', $inEggs($r->unit, $r->quantity), 'sold'));
            DB::table('sales_returns')->join('sale_lines', 'sale_lines.id', '=', 'sales_returns.sale_line_id')->where('sale_lines.product_id', $product->id)
                ->get(['sales_returns.returned_on', 'sale_lines.unit', 'sales_returns.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->returned_on, 'added', $inEggs($r->unit, $r->quantity), 'returned'));
        }

        // Birds, per batch.
        foreach (AnimalGroup::with('breed')->orderBy('code')->get() as $batch) {
            $key = 'batch-'.$batch->id;
            $items->push(['key' => $key, 'unit' => 'poultry', 'item' => $batch->name ?: $batch->code, 'detail' => collect(['Birds', $batch->code, $batch->breed?->name])->filter()->unique()->implode(' · '), 'measure' => 'birds', 'per_tray' => null]);
            if ($batch->received_date) $add($key, $batch->received_date->toDateString(), 'added', (float) $batch->quantity_received + (float) $batch->quantity_free - (float) $batch->transit_deaths, 'received');
            DB::table('mortality_records')->where('animal_group_id', $batch->id)->when(DB::getSchemaBuilder()->hasColumn('mortality_records', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
                ->get(['occurred_on', 'quantity_died', 'quantity_culled'])
                ->each(fn ($r) => $add($key, (string) $r->occurred_on, 'lost', (float) $r->quantity_died + (float) $r->quantity_culled, 'died / culled'));
            DB::table('bird_consumptions')->where('animal_group_id', $batch->id)->get(['consumed_on', 'quantity'])
                ->each(fn ($r) => $add($key, (string) $r->consumed_on, 'lost', (float) $r->quantity, 'used on the farm'));
            DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.animal_group_id', $batch->id)->where('sales.status', '!=', 'void')
                ->get(['sales.sale_date', 'sale_lines.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->sale_date, 'sold', (float) $r->quantity, 'sold'));
        }

        // Goats, rabbits and dogs, per group.
        foreach (DB::table('livestock_groups')->whereIn('unit_code', ['goats', 'rabbits', 'dogs'])->orderBy('group_code')->get() as $group) {
            $key = 'group-'.$group->id;
            $items->push(['key' => $key, 'unit' => $group->unit_code, 'item' => $group->group_code, 'detail' => $group->breed ?: 'Breed not recorded', 'measure' => 'animals', 'per_tray' => null]);
            $add($key, (string) $group->acquired_on, 'added', (float) $group->initial_quantity, $group->source ?: 'registered');
            DB::table('livestock_production_records')->where('livestock_group_id', $group->id)->where('production_type', 'births')->get(['recorded_on', 'quantity'])
                ->each(fn ($r) => $add($key, (string) $r->recorded_on, 'added', (float) $r->quantity, 'births'));
            DB::table('livestock_mortality_records')->where('livestock_group_id', $group->id)->get(['occurred_on', 'quantity'])
                ->each(fn ($r) => $add($key, (string) $r->occurred_on, 'lost', (float) $r->quantity, 'died'));
        }

        // Products brought in by paid farm tasks (e.g. manure): confirmed collections in, sales out.
        $taskProducts = DB::table('farm_task_types')->join('sale_products', 'sale_products.id', '=', 'farm_task_types.sale_product_id')
            ->select('sale_products.id', 'sale_products.name', 'sale_products.category', 'sale_products.unit', 'farm_task_types.measure')->get()->unique('id');
        foreach ($taskProducts as $product) {
            $key = 'product-'.$product->id;
            $unit = in_array($product->category, array_keys(self::UNITS), true) ? $product->category : 'poultry';
            if (str_contains(strtolower($product->name), 'goat')) $unit = 'goats';
            elseif (str_contains(strtolower($product->name), 'rabbit')) $unit = 'rabbits';
            $items->push(['key' => $key, 'unit' => $unit, 'item' => $product->name, 'detail' => 'Collected by farm tasks', 'measure' => $product->measure ?: $product->unit, 'per_tray' => null]);
            DB::table('farm_task_entries')->join('farm_task_types', 'farm_task_types.id', '=', 'farm_task_entries.farm_task_type_id')
                ->where('farm_task_types.sale_product_id', $product->id)->where('farm_task_entries.verification_status', 'approved')
                ->get(['farm_task_entries.done_on', 'farm_task_entries.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->done_on, 'added', (float) $r->quantity, 'collected'));
            DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.product_id', $product->id)->where('sales.status', '!=', 'void')
                ->get(['sales.sale_date', 'sale_lines.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->sale_date, 'sold', (float) $r->quantity, 'sold'));
            DB::table('sales_returns')->join('sale_lines', 'sale_lines.id', '=', 'sales_returns.sale_line_id')->where('sale_lines.product_id', $product->id)
                ->get(['sales_returns.returned_on', 'sales_returns.quantity'])
                ->each(fn ($r) => $add($key, (string) $r->returned_on, 'added', (float) $r->quantity, 'returned'));
        }

        // Garden produce, per crop and measure.
        $garden = DB::table('farm_unit_records')->where('unit_code', 'garden')->where('verification_status', 'approved')
            ->whereIn('record_type', ['harvest', 'loss', 'sale'])->whereNotNull('quantity')->orderBy('subject')->get();
        foreach ($garden as $record) {
            $measure = trim((string) $record->measure_unit) ?: 'units';
            $key = 'garden-'.mb_strtolower(trim($record->subject)).'|'.mb_strtolower($measure);
            if (! $items->contains('key', $key)) $items->push(['key' => $key, 'unit' => 'garden', 'item' => trim($record->subject), 'detail' => 'Garden produce', 'measure' => $measure, 'per_tray' => null]);
            $kind = ['harvest' => 'added', 'loss' => 'lost', 'sale' => 'sold'][$record->record_type];
            $add($key, (string) $record->record_date, $kind, (float) $record->quantity, ['harvest' => 'harvested', 'loss' => 'crop loss', 'sale' => 'sold'][$record->record_type]);
        }

        $this->items = $items->sortBy(fn ($i) => array_search($i['unit'], array_keys(self::UNITS)).'|'.$i['item'])->values();
        $this->events = $events;
    }
}
