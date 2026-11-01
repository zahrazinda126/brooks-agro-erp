<?php

namespace App\Services;

use App\Http\Controllers\Operations\FeedStockController;
use App\Models\AnimalGroup;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The facts in the CEO's daily report, for one day, in the order of her report: staffing, poultry
 * production per batch, the week's feed, other enterprises, the day's sales and outstanding payables.
 */
class CeoDailyReport
{
    public function facts(Carbon $date): array
    {
        $day = $date->toDateString();

        return [
            'staff' => $this->staff($day),
            'poultry' => $this->poultry($date),
            'feed' => $this->feed($date),
            'enterprises' => $this->enterprises($day),
            'sales' => $this->sales($day),
            'payables' => $this->payables($date),
        ];
    }

    private function staff(string $day): array
    {
        return [
            'total' => Employee::where('status', 'active')->count(),
            'present' => DB::table('employee_records')->where('type', 'attendance')->whereDate('recorded_on', $day)->distinct()->count('employee_id'),
        ];
    }

    /** One row per poultry batch on the farm that day: age, opening stock, losses, closing stock, eggs. */
    private function poultry(Carbon $date): array
    {
        $day = $date->toDateString();
        return AnimalGroup::with('breed')->where('department_id', DB::table('departments')->where('code', 'poultry')->value('id'))
            ->whereDate('received_date', '<=', $day)
            ->where(fn ($q) => $q->whereNull('closed_date')->orWhereDate('closed_date', '>=', $day))
            ->orderBy('received_date')->get()
            ->map(function (AnimalGroup $batch) use ($day) {
                $lossAfter = (int) DB::table('mortality_records')->where('animal_group_id', $batch->id)->whereDate('occurred_on', '>', $day)->where('status', '!=', 'rejected')->sum(DB::raw('quantity_died + quantity_culled'));
                $soldAfter = (int) DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.animal_group_id', $batch->id)->where('sales.status', '!=', 'void')->whereDate('sales.sale_date', '>', $day)->sum('sale_lines.quantity');
                $died = (int) DB::table('mortality_records')->where('animal_group_id', $batch->id)->whereDate('occurred_on', $day)->where('status', '!=', 'rejected')->sum('quantity_died');
                $culled = (int) DB::table('mortality_records')->where('animal_group_id', $batch->id)->whereDate('occurred_on', $day)->where('status', '!=', 'rejected')->sum('quantity_culled');
                $sold = (int) DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')->where('sale_lines.animal_group_id', $batch->id)->where('sales.status', '!=', 'void')->whereDate('sales.sale_date', $day)->sum('sale_lines.quantity');
                $closing = (int) $batch->current_quantity + $lossAfter + $soldAfter;
                $eggs = (int) DB::table('egg_collections')->where('animal_group_id', $batch->id)->whereDate('collected_on', $day)->where('status', 'approved')->sum('total_eggs');
                $ageDays = (int) $batch->received_date->diffInDays(Carbon::parse($day)) + (int) $batch->age_days_on_arrival;
                return [
                    'batch' => $batch->code, 'breed' => $batch->breed?->name ?? '—',
                    'age' => intdiv($ageDays, 7).' weeks'.($ageDays % 7 ? ' and '.($ageDays % 7).' '.($ageDays % 7 === 1 ? 'day' : 'days') : ''),
                    'opening' => $closing + $died + $culled + $sold, 'mortality' => $died + $culled, 'sold' => $sold, 'closing' => $closing,
                    'eggs' => $eggs, 'trays' => intdiv($eggs, 30), 'loose' => $eggs % 30,
                ];
            })->values()->all();
    }

    /** Feed delivered in the report's week (Monday to Sunday), with the price paid per kg. */
    private function feed(Carbon $date): array
    {
        $start = $date->copy()->startOfWeek()->toDateString();
        $end = $date->copy()->endOfWeek()->toDateString();
        $deliveries = DB::table('feed_delivery_verifications')->whereBetween('delivery_date', [$start, $end])->get(['id', 'status', 'reference']);
        $bags = DB::table('feed_delivery_bags')->whereIn('feed_delivery_verification_id', $deliveries->pluck('id'))->get()->groupBy('feed_name');
        $rows = [];
        foreach (FeedStockController::feedNames() as $feed) {
            if (! $bags->has($feed)) continue;
            $kg = (float) $bags[$feed]->sum(fn ($b) => $b->farm_weight_kg ?? $b->supplier_weight_kg);
            $price = $this->pricePerKg($feed, $end);
            $rows[] = ['item' => $feed, 'kg' => $kg, 'price' => $price, 'total' => round($kg * $price)];
        }
        $refs = $deliveries->pluck('reference')->filter()->all();
        $transport = $refs ? (float) DB::table('financial_transactions')->whereNull('reversed_at')->where('type', 'money_paid')->where('sub_account', 'Transport')
            ->where(fn ($q) => collect($refs)->each(fn ($ref) => $q->orWhere('description', 'like', '%'.$ref.'%')))->sum('amount') : 0.0;
        return [
            'week' => Carbon::parse($start)->format('d M').' – '.Carbon::parse($end)->format('d M Y'),
            'rows' => $rows, 'transport' => $transport,
            'delivered' => $deliveries->isNotEmpty() && $deliveries->every(fn ($d) => $d->status === 'received_at_farm'),
            'deliveries' => $deliveries->count(),
        ];
    }

    /** Latest price per kg paid for a feed: from supplier bills, else from direct purchases. */
    private function pricePerKg(string $feed, string $upTo): float
    {
        $line = DB::table('payable_lines')->join('payables', 'payables.id', '=', 'payable_lines.payable_id')
            ->whereRaw('lower(payable_lines.item_name) = ?', [mb_strtolower($feed)])->where('payable_lines.unit', 'kg')->where('payable_lines.quantity', '>', 0)
            ->whereDate('payables.invoice_date', '<=', $upTo)->latest('payables.invoice_date')->first(['payable_lines.amount', 'payable_lines.quantity']);
        if ($line) return round((float) $line->amount / (float) $line->quantity, 2);
        $paid = DB::table('financial_transactions')->whereNull('reversed_at')->where('type', 'money_paid')->where('entry_account', 'Feeds')
            ->where(fn ($q) => $q->where('sub_account', $feed)->orWhere('sub_account', $feed.' Meal')->orWhere('description', 'like', $feed.'%'))
            ->where('unit_price', '>', 0)->whereDate('transaction_date', '<=', $upTo)->latest('transaction_date')->value('unit_price');
        return (float) ($paid ?? 0);
    }

    private function enterprises(string $day): array
    {
        return DB::table('livestock_groups')->whereDate('acquired_on', '<=', $day)->where('status', '!=', 'closed')
            ->selectRaw('unit_code, sum(current_quantity) as animals, count(*) as groups')->groupBy('unit_code')->orderBy('unit_code')->get()
            ->map(fn ($r) => ['unit' => ucfirst($r->unit_code), 'animals' => (int) $r->animals, 'groups' => (int) $r->groups])->all();
    }

    /** The day's sales by product (internal farm use left out). */
    private function sales(string $day): array
    {
        return DB::table('sale_lines')->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.status', '!=', 'void')->where(fn ($q) => $q->whereNull('sales.is_staff_consumption')->orWhere('sales.is_staff_consumption', false))
            ->whereDate('sales.sale_date', $day)
            ->selectRaw('sale_lines.description, sale_lines.unit, sum(sale_lines.quantity) as quantity, sum(sale_lines.line_total) as amount')
            ->groupBy('sale_lines.description', 'sale_lines.unit')->orderByDesc('amount')->get()
            ->map(fn ($r) => ['item' => $r->description, 'quantity' => (float) $r->quantity, 'unit' => $r->unit, 'amount' => (float) $r->amount])->all();
    }

    /** What the farm owes each supplier at the end of the day: older debts, this year's, and what was paid that day. */
    private function payables(Carbon $date): array
    {
        $day = $date->toDateString();
        $yearStart = $date->copy()->startOfYear()->toDateString();
        $bills = DB::table('payables')->whereDate('invoice_date', '<=', $day)->get(['id', 'supplier_name', 'invoice_date', 'amount', 'item_name', 'description']);
        $paidBy = DB::table('payable_payments')->whereDate('paid_date', '<=', $day)->selectRaw('payable_id, sum(amount) as paid')->groupBy('payable_id')->pluck('paid', 'payable_id');
        $paidToday = DB::table('payable_payments')->whereDate('paid_date', $day)->selectRaw('payable_id, sum(amount) as paid')->groupBy('payable_id')->pluck('paid', 'payable_id');
        $lines = DB::table('payable_lines')->whereIn('payable_id', $bills->pluck('id'))->get(['payable_id', 'item_name'])->groupBy('payable_id');
        return $bills->groupBy(fn ($b) => trim($b->supplier_name))->map(function ($rows, $supplier) use ($paidBy, $paidToday, $lines, $yearStart) {
            $owed = fn ($b) => max(0.0, (float) $b->amount - (float) ($paidBy[$b->id] ?? 0));
            $older = (float) $rows->filter(fn ($b) => $b->invoice_date < $yearStart)->sum($owed);
            $current = (float) $rows->filter(fn ($b) => $b->invoice_date >= $yearStart)->sum($owed);
            $items = $rows->flatMap(fn ($b) => $lines->get($b->id, collect())->pluck('item_name')->whenEmpty(fn () => collect([$b->item_name ?: $b->description])))->filter()->unique()->take(4)->implode(', ');
            return ['supplier' => $supplier, 'service' => $items, 'older' => $older, 'current' => $current, 'paid_today' => (float) $rows->sum(fn ($b) => (float) ($paidToday[$b->id] ?? 0)), 'total' => $older + $current];
        })->filter(fn ($r) => $r['total'] > 0 || $r['paid_today'] > 0)->sortByDesc('total')->values()->all();
    }
}
