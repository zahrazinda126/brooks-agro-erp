<?php

namespace App\Models;

use App\Services\ChartOfAccounts;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FinancialTransaction extends Model
{
    /** Columns that describe an entry without changing the books. */
    private const METADATA = ['finance_account_id', 'reversed_at', 'reversed_by', 'reversal_reason', 'updated_at'];

    protected static function booted(): void
    {
        // Closed financial periods are locked for every writer (manual entry, sales, payroll, receivables).
        static::saving(function (FinancialTransaction $transaction) {
            if (! $transaction->finance_account_id && $transaction->entry_account && static::hasAccountLink()) {
                $transaction->finance_account_id = ChartOfAccounts::resolveId($transaction->entry_account);
            }
            // Marking an entry reversed (or linking its account) does not change the books, so it is allowed in closed periods.
            $bookkeeping = array_diff(array_keys($transaction->getDirty()), self::METADATA);
            if ($transaction->exists && $bookkeeping === []) return;
            static::assertPeriodOpen($transaction->transaction_date);
            if ($transaction->exists && $transaction->isDirty('transaction_date')) {
                static::assertPeriodOpen($transaction->getOriginal('transaction_date'));
            }
        });
        static::deleting(fn (FinancialTransaction $transaction) => static::assertPeriodOpen($transaction->getOriginal('transaction_date')));
    }

    private static ?bool $hasAccountLink = null;

    private static function hasAccountLink(): bool
    {
        // Only a positive answer is cached: older migrations save transactions before the column exists.
        return static::$hasAccountLink ?: (static::$hasAccountLink = Schema::hasColumn('financial_transactions', 'finance_account_id'));
    }

    public static function closedPeriodFor($date): ?object
    {
        if (! $date) return null;
        $day = Carbon::parse($date)->toDateString();
        return DB::table('finance_periods')->where('status', 'closed')
            ->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day)->first();
    }

    public static function assertPeriodOpen($date): void
    {
        if ($period = static::closedPeriodFor($date)) {
            throw ValidationException::withMessages([
                'transaction_date' => 'The financial period "'.$period->name.'" is closed. Choose a date in an open period or ask Finance to reopen it.',
            ]);
        }
    }

    protected $fillable = ['construction_project_id', 'construction_worker_id', 'transaction_date', 'type', 'transaction_category', 'account_group', 'entry_account', 'sub_account', 'finance_account_id', 'department_id', 'cost_centre', 'account', 'counterparty', 'recipient_account', 'amount', 'quantity', 'unit_price', 'payment_method', 'cheque_number', 'reference', 'description', 'recorded_by', 'source', 'reversal_of_id', 'reversed_at', 'reversed_by', 'reversal_reason'];

    protected function casts(): array
    {
        return ['reversed_at' => 'datetime', 'transaction_date' => 'date', 'amount' => 'decimal:2', 'quantity' => 'decimal:2', 'unit_price' => 'decimal:2'];
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function financeAccount(): BelongsTo
    {
        return $this->belongsTo(FinanceAccount::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /** Only standalone Finance screen entries: others also changed a receivable, bill, salary balance or stock. */
    public function isReversible(): bool
    {
        return $this->source === 'finance_entry' && ! $this->reversed_at && ! $this->reversal_of_id;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
