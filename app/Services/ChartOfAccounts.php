<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links free-text account names to the chart of accounts (finance_accounts).
 *
 * The workbook and the ERP spell the same account differently ("Internet_or_Email",
 * "Internet / Email", "cash", "Cash"). Names are compared after normalising case,
 * underscores, "&"/"and", "/"/"or" and known misspellings; config
 * finance_entry.account_aliases covers names whose wording genuinely differs.
 * The original text on each transaction is never changed.
 */
class ChartOfAccounts
{
    private const SPELLING = ['defered' => 'deferred', 'recrutiment' => 'recruitment', 'licence' => 'license'];

    private static ?array $lookup = null;

    public static function normalise(?string $name): string
    {
        $value = mb_strtolower((string) $name);
        $value = str_replace(['_', '&', '/'], [' ', ' and ', ' or '], $value);
        $value = strtr($value, self::SPELLING);
        $value = preg_replace('/[^a-z0-9 ]+/', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    /** The finance_accounts id for an account name, or null when it is not in the chart. */
    public static function resolveId(?string $name): ?int
    {
        $key = self::normalise($name);
        if ($key === '') return null;

        $lookup = self::lookup();

        // Customer receivable accounts carry the workbook's "C " prefix (e.g. "C Kisseka_Denis").
        return $lookup[$key] ?? $lookup['c '.$key] ?? null;
    }

    public static function forgetLookup(): void
    {
        self::$lookup = null;
    }

    private static function lookup(): array
    {
        if (self::$lookup !== null) return self::$lookup;
        if (! Schema::hasTable('finance_accounts')) return self::$lookup = [];

        $accounts = DB::table('finance_accounts')->where('is_active', true)->get(['id', 'code', 'name', 'workbook_label', 'is_group']);
        $byCode = $accounts->keyBy('code');
        $map = [];
        // Posting accounts first so a group heading never shadows a same-named posting account.
        foreach ($accounts->sortBy(fn ($account) => $account->is_group ? 1 : 0) as $account) {
            foreach ([$account->name, $account->workbook_label] as $label) {
                $key = self::normalise($label);
                if ($key !== '' && ! isset($map[$key])) $map[$key] = (int) $account->id;
            }
        }
        foreach (config('finance_entry.account_aliases', []) as $alias => $code) {
            if ($account = $byCode->get((string) $code)) $map[self::normalise($alias)] = (int) $account->id;
        }

        return self::$lookup = $map;
    }

    /**
     * Add the workbook chart (database/data/workbook_chart_of_accounts.php) and the ERP's
     * coded profitability accounts. Existing rows are kept; running it again is harmless.
     */
    public static function seedWorkbookChart(): int
    {
        $now = now();
        $added = 0;
        $rows = collect(require database_path('data/workbook_chart_of_accounts.php'))->keyBy('code');

        // The ERP's report codes take their on-screen names; the workbook spelling stays in workbook_label.
        $sectionParent = fn (string $code) => match (true) {
            str_starts_with($code, '4011') => '4011000',
            str_starts_with($code, '4012') => '4012000',
            str_starts_with($code, '5') => '5010000',
            default => '6000000',
        };
        foreach (config('finance_entry.profitability_accounts', []) as $accounts) {
            foreach ($accounts as $code => $name) {
                $code = (string) $code;
                $row = $rows->get($code, ['code' => $code, 'workbook_label' => null, 'is_group' => false,
                    'parent_code' => $sectionParent($code), 'sort_order' => 5000 + $rows->count(),
                    'type' => ['4' => 'income', '5' => 'cogs'][$code[0]] ?? 'expense', 'statement' => 'profit_loss']);
                $rows->put($code, ['name' => $name] + $row);
            }
        }

        foreach ($rows as $row) {
            if (DB::table('finance_accounts')->where('code', $row['code'])->exists()) continue;
            DB::table('finance_accounts')->insert([
                'code' => $row['code'], 'name' => mb_substr($row['name'], 0, 160), 'workbook_label' => $row['workbook_label'],
                'type' => $row['type'], 'statement' => $row['statement'], 'is_group' => $row['is_group'],
                'sort_order' => $row['sort_order'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $added++;
        }

        $ids = DB::table('finance_accounts')->pluck('id', 'code');
        foreach ($rows as $row) {
            if ($row['parent_code'] && isset($ids[$row['parent_code']])) {
                DB::table('finance_accounts')->where('code', $row['code'])->whereNull('parent_id')->update(['parent_id' => $ids[$row['parent_code']]]);
            }
        }
        self::forgetLookup();

        return $added;
    }

    /**
     * Fill finance_account_id on transactions that do not have one yet.
     * Returns [linked count, unmatched names with their row counts].
     */
    public static function linkTransactions(bool $dryRun = false): array
    {
        $linked = 0;
        $unmatched = [];
        DB::table('financial_transactions')->whereNull('finance_account_id')
            ->select('entry_account', DB::raw('count(*) as total'))->groupBy('entry_account')->get()
            ->each(function ($group) use (&$linked, &$unmatched, $dryRun) {
                $id = self::resolveId($group->entry_account);
                if (! $id) {
                    $unmatched[$group->entry_account ?? '(no entry account)'] = (int) $group->total;
                    return;
                }
                if (! $dryRun) {
                    DB::table('financial_transactions')->whereNull('finance_account_id')
                        ->where('entry_account', $group->entry_account)->update(['finance_account_id' => $id]);
                }
                $linked += (int) $group->total;
            });

        return [$linked, $unmatched];
    }
}
