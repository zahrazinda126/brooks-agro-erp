<?php

namespace App\Console\Commands;

use App\Services\WorkbookImportStager;
use Illuminate\Console\Command;

class StageFinanceWorkbook extends Command
{
    protected $signature = 'finance:stage-workbook {path? : Extract JSON (default storage/app/finance-workbook-extract-current.json)}';

    protected $description = 'Stage the finance workbook extract for review (dry run: nothing is posted to the books)';

    public function handle(WorkbookImportStager $stager): int
    {
        $path = $this->argument('path') ?: storage_path('app/finance-workbook-extract-current.json');
        $result = $stager->stage($path);

        $this->info("Staged {$result['rows']} rows ({$result['date_min']} to {$result['date_max']}) in import batch #{$result['batch_id']}.");
        $this->table(['Type', 'Rows', 'Amount (UGX)'], collect($result['by_type'])->map(fn ($v, $type) => [$type, $v['rows'], number_format($v['amount'])])->values());
        $this->line("Exact duplicates to review: {$result['duplicates']}");
        $this->line("Rows without a 'Reviewed' QA status: {$result['unreviewed']}");
        if ($result['revenue_reconciles'] !== null) $this->line('Revenue reconciles to the workbook analysis: '.($result['revenue_reconciles'] ? 'yes' : 'NO'));
        if ($result['unmatched_accounts']) {
            $this->warn(count($result['unmatched_accounts']).' account name(s) are not in the chart of accounts (top 15):');
            $this->table(['Account', 'Rows'], collect($result['unmatched_accounts'])->take(15)->map(fn ($n, $name) => [$name, $n])->values());
        }
        $this->comment('Nothing was posted. Posting needs opening balances at 30 June 2025 and review of the rows above.');

        return self::SUCCESS;
    }
}
