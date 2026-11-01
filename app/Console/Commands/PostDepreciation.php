<?php

namespace App\Console\Commands;

use App\Models\FinancialTransaction;
use App\Models\FixedAsset;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Monthly straight-line depreciation for active fixed assets with a useful life.
 * Posts Dr "Depreciation - X" / Cr "X Accumulated Depreciation" at month end.
 * Safe to re-run: each asset/month has one reference (DEP-{asset}-{YYYYMM}); stops at cost.
 */
class PostDepreciation extends Command
{
    protected $signature = 'finance:post-depreciation {month? : Month to post, YYYY-MM (default: last month)} {--dry-run}';

    protected $description = 'Post monthly straight-line depreciation for fixed assets';

    /** Asset register category → workbook depreciation account (see profitability_accounts). Land is not depreciated. */
    private const ACCOUNT_FOR = [
        'Buildings' => 'Buildings', 'Farm Structures' => 'Buildings',
        'Plant & Machinery' => 'Machinery', 'Processing & Packaging Equipment' => 'Machinery', 'Workshop & Maintenance Equipment' => 'Machinery',
        'Vehicles' => 'Motor Vehicles',
        'Office Furniture & Equipment' => 'Office Equipment', 'Computers & IT Equipment' => 'Office Equipment', 'Security Equipment' => 'Office Equipment',
    ];

    public function handle(): int
    {
        $month = $this->argument('month') ? Carbon::createFromFormat('!Y-m', $this->argument('month'))->startOfMonth() : now()->subMonthNoOverflow()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $poster = User::where('is_active', true)->whereHas('role', fn ($r) => $r->where('code', 'finance-manager'))->value('id')
            ?? User::where('is_active', true)->whereHas('role', fn ($r) => $r->where('is_superuser', true))->value('id');
        if (! $poster && ! $this->option('dry-run')) {
            $this->error('No active Finance Manager or superuser account to record the entries.');
            return self::FAILURE;
        }

        $posted = $total = 0;
        foreach (FixedAsset::where('status', 'active')->whereNotNull('useful_life_years')->where('useful_life_years', '>', 0)->where('category', '!=', 'Land')->whereDate('purchase_date', '<=', $monthEnd)->get() as $asset) {
            $reference = 'DEP-'.$asset->id.'-'.$month->format('Ym');
            if (FinancialTransaction::where('reference', $reference)->exists()) continue;
            $monthly = round((float) $asset->purchase_cost / ($asset->useful_life_years * 12), 2);
            $already = (float) FinancialTransaction::where('source', 'depreciation')->where('reference', 'like', 'DEP-'.$asset->id.'-%')->sum('amount');
            $amount = min($monthly, round((float) $asset->purchase_cost - $already, 2));
            if ($amount <= 0) continue;

            $class = self::ACCOUNT_FOR[$asset->category] ?? 'Farm Equipment';
            if (! $this->option('dry-run')) {
                FinancialTransaction::create([
                    'transaction_date' => $monthEnd->toDateString(), 'type' => 'expense_recognized', 'transaction_category' => 'depreciation',
                    'account_group' => 'Operating Costs', 'entry_account' => 'Depreciation - '.$class,
                    'account' => $class.' Accumulated Depreciation', 'counterparty' => $asset->name, 'amount' => $amount,
                    'reference' => $reference, 'description' => 'Straight-line depreciation for '.$month->format('F Y').': '.$asset->name,
                    'recorded_by' => $poster, 'source' => 'depreciation',
                ]);
            }
            $posted++;
            $total += $amount;
        }

        $this->info(($this->option('dry-run') ? 'Would post ' : 'Posted ')."{$posted} depreciation entr".($posted === 1 ? 'y' : 'ies').' for '.$month->format('F Y').', UGX '.number_format($total, 2).'.');

        return self::SUCCESS;
    }
}
