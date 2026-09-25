<?php

namespace App\Services;

use App\Models\AnimalGroup;
use App\Models\FinancialTransaction;
use App\Models\SaleLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UnitEconomicsService
{
    private const UNITS = ['Poultry', 'Goats', 'Rabbits', 'Dogs', 'Garden'];

    public function report(string $from, string $to, ?string $unit = null): array
    {
        $selected = $unit && in_array($unit, self::UNITS, true) ? $unit : null;
        $expenses = FinancialTransaction::where('type', 'money_paid')
            ->whereBetween('transaction_date', [$from, $to])->get();
        $receipts = FinancialTransaction::where(function ($query) {
            $query->where(function ($cash) {
                $cash->where('type', 'money_received')->where(function ($classification) {
                    $classification->whereNull('transaction_category')->orWhere('transaction_category', '!=', 'customer_prepayment');
                });
            })->orWhere('type', 'revenue_recognized');
        })
            ->whereBetween('transaction_date', [$from, $to])->get();

        $unitRows = collect(self::UNITS)->map(function (string $name) use ($expenses, $from, $to) {
            $spent = $expenses->filter(fn ($row) => strcasecmp((string) $row->cost_centre, $name) === 0)->sum('amount');
            $revenue = $this->salesRevenue($name, $from, $to);

            return ['unit'=>$name, 'spent'=>(float)$spent, 'revenue'=>$revenue, 'result'=>$revenue-(float)$spent];
        });

        $allocated = $unitRows->sum('spent');
        $unallocated = max(0, (float)$expenses->sum('amount') - $allocated);
        $lifeCycles = $selected === 'Poultry'
            ? $this->poultryLifeCycles($to)
            : ($selected && $selected !== 'Garden' ? $this->livestockLifeCycles(strtolower($selected), $to) : collect());
        $accountProfitability = $this->accountProfitability($from, $to);

        return [
            'units'=>$unitRows,
            'lifeCycles'=>$lifeCycles,
            'selectedUnit'=>$selected,
            'totalRevenue'=>(float)$receipts->sum('amount'),
            'totalSpent'=>(float)$expenses->sum('amount'),
            'unallocated'=>$unallocated,
            'from'=>$from,
            'to'=>$to,
            'unitOptions'=>self::UNITS,
            'accountProfitability'=>$accountProfitability,
        ];
    }

    private function accountProfitability(string $from, string $to): array
    {
        $catalog = config('finance_entry.profitability_accounts', []);
        $values = collect($catalog)->mapWithKeys(fn ($accounts, $section) => [
            $section => collect($accounts)->mapWithKeys(fn ($name, $code) => [$name => 0.0]),
        ]);

        $saleLines = SaleLine::whereHas('sale', fn ($query) => $query
            ->whereBetween('sale_date', [$from, $to])->where('status', '!=', 'void'))->get();
        foreach ($saleLines as $line) {
            $text = str((string) $line->category.' '.(string) $line->description)->lower();
            $account = match (true) {
                $text->contains('damaged') => 'Sales Damaged Eggs',
                $text->contains('starter') => 'Sales Starter Eggs',
                $text->contains('medium') => 'Sales Medium Eggs',
                $text->contains('large') => 'Sales Large Eggs',
                $text->contains('off layer') => 'Sales Off Layers',
                $text->contains('manure') => 'Sales Manure',
                $text->contains('rabbit') && $text->contains('meat') => 'Rabbit Meat Sales',
                $text->contains('goat') && $text->contains('meat') => 'Goat Meat Sales',
                $text->contains('garden') => 'Garden Sales',
                $text->contains('rabbit') => 'Rabbit Sales',
                $text->contains('goat') => 'Goat Sales',
                default => 'Other Sales',
            };
            $revenueValues = $values->get('Revenue');
            if ($revenueValues?->has($account)) $revenueValues->put($account, (float) $revenueValues->get($account) + (float) $line->line_total);
        }

        $transactions = FinancialTransaction::whereBetween('transaction_date', [$from, $to])->get();
        $welfare = ['Staff Medication', 'Condolence Support', 'Medical Support', 'Emergency Support', 'Other Staff Welfare'];
        foreach ($transactions as $transaction) {
            $account = (string) $transaction->entry_account;
            $account = match ($account) {
                'Interest Income' => 'Interest Earned',
                'Dividend Income' => 'Dividend Earned',
                'Poultry Manure' => 'Sales Manure',
                'goats manure Sales' => 'Goats Manure Sales',
                'rabbits manure' => 'Rabbits Manure Sales',
                default => $account,
            };
            $revenueValues = $values->get('Revenue');
            if ($transaction->type === 'money_received' && $revenueValues?->has($account)) {
                $revenueValues->put($account, (float) $revenueValues->get($account) + (float) $transaction->amount);
                continue;
            }
            if ($transaction->type !== 'money_paid' && ! str_starts_with($account, 'Depreciation - ')) continue;
            if (in_array($account, ['Salary Withdrawal', 'Staff Advance'], true)) continue;
            if (in_array($account, $welfare, true)) $account = 'Staff Welfare Related Costs';
            if ($account === 'Casual Wages') $account = 'Salary Expense';
            if ($account === 'Staff Reimbursement') $account = 'Other Expenses';
            foreach (['Cost of Sales', 'Operating Costs'] as $section) {
                $sectionValues = $values->get($section);
                if ($sectionValues?->has($account)) $sectionValues->put($account, (float) $sectionValues->get($account) + (float) $transaction->amount);
            }
        }

        $sections = collect($catalog)->map(function ($accounts, $section) use ($values) {
            return [
                'name'=>$section,
                'rows'=>collect($accounts)->map(fn ($name, $code) => ['code'=>(string)$code, 'account'=>$name, 'amount'=>(float)$values[$section][$name]])->values(),
                'total'=>(float)$values[$section]->sum(),
            ];
        })->values();
        $revenue = (float) data_get($sections->firstWhere('name', 'Revenue'), 'total', 0);
        $costOfSales = (float) data_get($sections->firstWhere('name', 'Cost of Sales'), 'total', 0);
        $operatingCosts = (float) data_get($sections->firstWhere('name', 'Operating Costs'), 'total', 0);

        return ['sections'=>$sections, 'grossProfit'=>$revenue-$costOfSales, 'netProfit'=>$revenue-$costOfSales-$operatingCosts];
    }

    private function salesRevenue(string $unit, string $from, string $to): float
    {
        $categories = match ($unit) {
            'Poultry' => ['eggs', 'poultry', 'manure'],
            'Goats' => ['goats', 'goat'],
            'Rabbits' => ['rabbits', 'rabbit'],
            'Dogs' => ['dogs', 'dog'],
            'Garden' => ['garden'],
        };

        return (float) SaleLine::whereHas('sale', fn ($query) => $query
            ->whereBetween('sale_date', [$from, $to])->where('status', '!=', 'void'))
            ->where(function ($query) use ($categories) {
                $query->whereIn('category', $categories)
                    ->orWhereHas('product', fn ($product) => $product->whereIn('category', $categories));
            })->sum('line_total');
    }

    private function poultryLifeCycles(string $to): Collection
    {
        return AnimalGroup::with('breed')->whereDate('received_date', '<=', $to)
            ->orderByDesc('received_date')->get()->map(function (AnimalGroup $batch) use ($to) {
                $feed = DB::table('feed_consumptions')->leftJoin('feed_types', 'feed_types.id', '=', 'feed_consumptions.feed_type_id')
                    ->where('feed_consumptions.animal_group_id', $batch->id)->where('feed_consumptions.used_on', '<=', $to)
                    ->where('feed_consumptions.status', 'approved')
                    ->sum(DB::raw('feed_consumptions.quantity_kg * coalesce(feed_types.cost_per_kg, 0)'));
                $health = DB::table('health_events')->where('animal_group_id', $batch->id)
                    ->where('occurred_on', '<=', $to)->sum('cost');
                $acquisition = (float)$batch->total_cost;
                $total = $acquisition + (float)$feed + (float)$health;
                $birds = max(1, $batch->totalReceived());

                return ['code'=>$batch->code, 'description'=>$batch->breed?->name ?? $batch->name,
                    'acquired_on'=>$batch->received_date, 'initial'=>$batch->totalReceived(),
                    'remaining'=>$batch->current_quantity, 'acquisition'=>$acquisition, 'feed'=>(float)$feed,
                    'health'=>(float)$health, 'total'=>$total, 'per_animal'=>$total/$birds,
                    'stage'=>$batch->stage()];
            });
    }

    private function livestockLifeCycles(string $unit, string $to): Collection
    {
        return DB::table('livestock_groups')->where('unit_code', $unit)->where('acquired_on', '<=', $to)
            ->orderByDesc('acquired_on')->get()->map(function ($group) use ($to) {
                $feed = (float)DB::table('livestock_feed_records')->where('livestock_group_id', $group->id)
                    ->where('recorded_on', '<=', $to)->whereIn('action', ['issued', 'fed'])->sum('cost');
                $health = (float)DB::table('livestock_health_records')->where('livestock_group_id', $group->id)
                    ->where('recorded_on', '<=', $to)->sum('cost');
                $acquisition = (float)$group->acquisition_cost;
                $total = $acquisition + $feed + $health;

                return ['code'=>$group->group_code, 'description'=>$group->breed ?: ucfirst($group->unit_code),
                    'acquired_on'=>$group->acquired_on, 'initial'=>$group->initial_quantity,
                    'remaining'=>$group->current_quantity, 'acquisition'=>$acquisition, 'feed'=>$feed,
                    'health'=>$health, 'total'=>$total,
                    'per_animal'=>$total/max(1, (int)$group->initial_quantity), 'stage'=>$group->status];
            });
    }
}
