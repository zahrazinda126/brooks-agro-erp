<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the finance workbook extract (tools/ → storage/app/finance-workbook-extract*.json)
 * into finance_import_rows for review. Idempotent: staging the same file again updates
 * the same batch. Nothing is posted to financial_transactions.
 */
class WorkbookImportStager
{
    public function stage(string $path, ?int $userId = null): array
    {
        if (! is_file($path)) throw new RuntimeException("Extract not found: {$path}");
        $extract = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $rows = $extract['transactions'] ?? throw new RuntimeException('The extract has no transactions list.');
        $fileHash = hash_file('sha256', $path);

        $batchId = DB::table('finance_import_batches')->where('file_hash', $fileHash)->value('id')
            ?? DB::table('finance_import_batches')->insertGetId(['filename' => basename($path), 'file_hash' => $fileHash, 'status' => 'staging', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);

        $seen = [];
        $summary = ['rows' => 0, 'duplicates' => 0, 'unreviewed' => 0, 'by_type' => [], 'unmatched_accounts' => [], 'date_min' => null, 'date_max' => null];
        $now = now();

        foreach (array_chunk($rows, 500, true) as $chunk) {
            $insert = [];
            foreach ($chunk as $index => $row) {
                $hash = hash('sha256', json_encode($row));
                $duplicate = isset($seen[$hash]);
                $seen[$hash] = true;
                $date = ! empty($row['date']) ? Carbon::parse($row['date'])->toDateString() : null;
                $offset = $row['offset_account'] ?? $row['particular'] ?? null;
                $accountId = ChartOfAccounts::resolveId($row['account'] ?? null);
                $offsetId = ChartOfAccounts::resolveId($offset);
                $qa = trim((string) ($row['qa_status'] ?? '')) ?: null;
                $type = $row['transaction_type'] ?? 'Unknown';

                $summary['rows']++;
                $summary['duplicates'] += $duplicate ? 1 : 0;
                $summary['unreviewed'] += $qa === 'Reviewed' ? 0 : 1;
                $summary['by_type'][$type]['rows'] = ($summary['by_type'][$type]['rows'] ?? 0) + 1;
                $summary['by_type'][$type]['amount'] = round(($summary['by_type'][$type]['amount'] ?? 0) + (float) ($row['amount'] ?? 0), 2);
                foreach ([[$row['account'] ?? null, $accountId], [$offset, $offsetId]] as [$name, $id]) {
                    if ($name && ! $id) $summary['unmatched_accounts'][$name] = ($summary['unmatched_accounts'][$name] ?? 0) + 1;
                }
                if ($date) {
                    $summary['date_min'] = min($summary['date_min'] ?? $date, $date);
                    $summary['date_max'] = max($summary['date_max'] ?? $date, $date);
                }

                $insert[] = [
                    'finance_import_batch_id' => $batchId, 'source_row' => $index + 1, 'content_hash' => $hash,
                    'transaction_date' => $date, 'transaction_type' => mb_substr($type, 0, 60),
                    'account_group' => $this->text($row['account_group'] ?? null), 'account' => $this->text($row['account'] ?? null),
                    'finance_account_id' => $accountId, 'offset_account' => $this->text($offset), 'offset_finance_account_id' => $offsetId,
                    'amount' => (float) ($row['amount'] ?? 0), 'quantity' => is_numeric($row['quantity'] ?? null) ? $row['quantity'] : null,
                    'unit_price' => is_numeric($row['unit_price'] ?? null) ? $row['unit_price'] : null,
                    'business_partner' => $this->text($row['business_partner'] ?? null), 'department' => $this->text($row['department'] ?? null, 120),
                    'workbook_transaction_id' => isset($row['transaction_id']) ? (string) $row['transaction_id'] : null,
                    'qa_status' => $qa ? mb_substr($qa, 0, 40) : null, 'is_duplicate' => $duplicate,
                    'original' => json_encode($row), 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            // Re-staging keeps any review decision already made on a row.
            DB::table('finance_import_rows')->upsert($insert, ['finance_import_batch_id', 'source_row'],
                ['content_hash', 'transaction_date', 'transaction_type', 'account_group', 'account', 'finance_account_id', 'offset_account', 'offset_finance_account_id', 'amount', 'quantity', 'unit_price', 'business_partner', 'department', 'workbook_transaction_id', 'qa_status', 'is_duplicate', 'original', 'updated_at']);
        }

        arsort($summary['unmatched_accounts']);
        $revenue = $summary['by_type']['Revenue']['amount'] ?? 0;
        $expected = data_get($extract, 'analysis.by_type.Revenue.amount');
        $summary['revenue_reconciles'] = $expected === null ? null : abs($revenue - (float) $expected) < 0.01;

        DB::table('finance_import_batches')->where('id', $batchId)->update([
            'status' => 'staged', 'source_rows' => $summary['rows'], 'imported_rows' => 0,
            'error_rows' => $summary['duplicates'], 'summary' => json_encode($summary), 'updated_at' => now(),
        ]);

        return ['batch_id' => $batchId] + $summary;
    }

    private function text($value, int $max = 160): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
