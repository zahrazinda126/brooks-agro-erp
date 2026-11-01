<?php

namespace App\Console\Commands;

use App\Services\ChartOfAccounts;
use Illuminate\Console\Command;

class LinkFinanceAccounts extends Command
{
    protected $signature = 'finance:link-accounts {--dry-run : Report what would be linked without saving}';

    protected $description = 'Link financial transactions to the chart of accounts and list account names that need an alias';

    public function handle(): int
    {
        $added = $this->option('dry-run') ? 0 : ChartOfAccounts::seedWorkbookChart();
        [$linked, $unmatched] = ChartOfAccounts::linkTransactions((bool) $this->option('dry-run'));

        if ($added) $this->info("Chart of accounts: {$added} account(s) added.");
        $this->info(($this->option('dry-run') ? 'Would link ' : 'Linked ')."{$linked} transaction(s).");
        if ($unmatched) {
            $this->warn('Not in the chart of accounts — add the account or an alias in config/finance_entry.php (account_aliases):');
            $this->table(['Entry account', 'Transactions'], collect($unmatched)->map(fn ($count, $name) => [$name, $count])->values());
        }

        return self::SUCCESS;
    }
}
