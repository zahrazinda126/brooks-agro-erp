<?php

namespace App\Http\Controllers;

use App\Models\{AnimalGroup, Approval, AuditLog, User, Communication, CustomerPrepayment, EggCollection, Employee, EmployeeRecord, FinancialTransaction, InventoryItem, InventoryMovement, InventoryStore, MortalityRecord, Payable, PayrollRun, Receivable, Sale};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Landing page for a sidebar section: a short summary with simple charts, then the
 * section's screens as cards (filled in from the sidebar by the layout script).
 *
 * Sections are defined per role in the sidebar, so the summary is chosen from the
 * section's name. Every panel is shown only when the account may open that area.
 */
class SectionOverviewController extends Controller
{
    /** Section-name pattern => summary panels. First matching patterns win, several may apply. */
    private const PANELS = [
        // Decisions is about requests waiting for a decision only, not money or production.
        '/decision/' => ['approvals'],
        '/report|board|executive|oversight|governance/' => ['money', 'poultry', 'sales', 'approvals'],
        '/cash|bank|money/' => ['money'],
        '/daily operation|daily work|today/' => ['daily'],
        '/accounting|ledger|books|^accounts?$|finance/' => ['accounting'],
        '/customer|receivable/' => ['customers'],
        '/supplier|payable|purchas|procure/' => ['suppliers'],
        '/inventory|stock|store|feed/' => ['stock'],
        '/payroll|salary|wage/' => ['payroll'],
        '/people|employee|staff|human|hr\b|welfare|attendance|development/' => ['people'],
        // Farm Units (Production Manager) shows every unit side by side, as the Farm Hub production control room.
        '/^farm units?$/' => ['units'],
        '/poultry|monitor|production|bird|egg|livestock/' => ['poultry'],
        '/sale/' => ['sales'],
        '/approval/' => ['approvals'],
        '/admin|govern|setting|security|audit/' => ['administration'],
        '/communication|message|update/' => ['messages'],
    ];

    public function show(Request $request, string $section): View|\Illuminate\Http\RedirectResponse
    {
        $name = trim((string) $request->query('name')) ?: Str::headline($section);
        $label = Str::lower($name);

        // Sales sells farm produce, not store items: their Inventory opens the sellable stock table.
        $user = $request->user();
        // Sales: their sections open the working page itself, not a summary.
        if ($user->roles->pluck('code')->push($user->role?->code)->contains('sales-manager')) {
            if (preg_match('/inventory|stock/', $label)) return redirect()->route('sales.index', ['screen' => 'inventory']);
            if (preg_match('/daily sale/', $label)) return redirect()->route('sales.index', ['screen' => 'daily']);
        }
        // Finance: Receivables and Reports have their own designed pages (receivables board, Reports Centre).
        if ($user->canAccess('receivables') && ($label === 'receivables' || ($label === 'customers' && $user->roles->pluck('code')->push($user->role?->code)->contains('finance-manager')))) return redirect()->route('finance.receivables.index');
        if ($user->canAccess('transactions') && $label === 'finance posting') return redirect()->route('finance.posting.index');
        if ($user->canAccess('reports') && $label === 'reports' && $user->roles->pluck('code')->push($user->role?->code)->contains('finance-manager')) return redirect()->route('finance.reports.index');
        $panels = collect(self::PANELS)->filter(fn ($keys, $pattern) => preg_match($pattern, $label))->flatten()->unique();
        $user = $request->user();

        $summaries = $panels->map(fn (string $panel) => $this->may($user, $panel) ? $this->{'panel'.Str::studly($panel)}() : null)
            ->filter()->values();

        // Nothing to summarise for this section: open its first screen instead of an empty page.
        $first = (string) $request->query('first', '');
        if ($summaries->isEmpty() && str_starts_with($first, '/') && ! str_starts_with($first, '//')) {
            return redirect($first);
        }

        return view('section-overview', ['section' => $section, 'name' => $name, 'summaries' => $summaries]);
    }

    private function may($user, string $panel): bool
    {
        if ($user->isSuperuser()) return true;
        // The Production Manager sees only production: no money, accounting, customers, suppliers, payroll, sales or HR summaries.
        if ($user->seesOnlyProductionReports() && in_array($panel, ['money', 'daily', 'accounting', 'customers', 'suppliers', 'payroll', 'sales', 'people'], true)) return false;
        $modules = match ($panel) {
            'money' => ['transactions', 'cash-book', 'bank-book', 'general-ledger', 'reports', 'finance'],
            'daily' => ['transactions', 'finance'],
            'accounting' => ['general-ledger', 'transactions', 'reports', 'finance'],
            'customers' => ['receivables', 'customers', 'sales'],
            'suppliers' => ['payables', 'purchases'],
            'stock' => ['inventory', 'feed-received', 'goods-received'],
            'payroll' => ['payroll'],
            'people' => ['hr', 'attendance', 'staff'],
            'poultry' => ['batches', 'egg-entry', 'health', 'mortality-assess', 'reports'],
            'units' => ['batches', 'goats', 'rabbits', 'dogs', 'garden', 'production', 'reports'],
            'sales' => ['sales'],
            'approvals' => ['approvals'],
            'administration' => ['approvals', 'audit', 'security', 'settings'],
            'messages' => ['messages'],
        };
        return $panel === 'messages' || collect($modules)->contains(fn ($key) => $user->canAccess($key));
    }

    /** @return array{title:string, stats:array, charts:array} */
    private function panelMoney(): array
    {
        $balances = [];
        foreach (FinancialTransaction::orderBy('transaction_date')->get(['type', 'account', 'counterparty', 'amount']) as $t) {
            $amount = (float) $t->amount;
            if ($t->type === 'money_received') $balances[$t->account] = ($balances[$t->account] ?? 0) + $amount;
            if ($t->type === 'money_paid') $balances[$t->account] = ($balances[$t->account] ?? 0) - $amount;
            if ($t->type === 'transfer') {
                $balances[$t->account] = ($balances[$t->account] ?? 0) - $amount;
                if ($t->counterparty) $balances[$t->counterparty] = ($balances[$t->counterparty] ?? 0) + $amount;
            }
        }
        $isBank = fn ($account) => (bool) preg_match('/bank|centenary|i&m|mobile|mtn|airtel|deposit/i', (string) $account);
        $bank = collect($balances)->filter(fn ($v, $account) => $isBank($account))->sum();
        $cash = collect($balances)->reject(fn ($v, $account) => $isBank($account) || blank($account))->sum();
        [$in, $out] = [$this->daily(FinancialTransaction::where('type', 'money_received'), 'transaction_date', 'amount'),
                       $this->daily(FinancialTransaction::where('type', 'money_paid'), 'transaction_date', 'amount')];

        return ['title' => 'Money', 'stats' => [
            ['Cash on hand', $this->money($cash)], ['Bank & mobile money', $this->money($bank)],
            ['Money in, last 14 days', $this->money(collect($in)->sum('value'))], ['Money out, last 14 days', $this->money(collect($out)->sum('value'))],
        ], 'charts' => [
            ['title' => 'Balance by account', 'unit' => 'UGX', 'horizontal' => true, 'signed' => true, 'wide' => true,
                'bars' => collect($balances)->filter(fn ($v, $account) => filled($account) && round($v) != 0)->sortDesc()
                    ->map(fn ($value, $account) => ['label' => $account, 'value' => round($value)])->values()->all()],
            ['title' => 'Money received per day', 'unit' => 'UGX', 'bars' => $in],
            ['title' => 'Money paid out per day', 'unit' => 'UGX', 'tone' => 'out', 'bars' => $out],
        ]];
    }

    /** Today's finance work: what was entered, money in/out today, where this week's money went. */
    private function panelDaily(): array
    {
        $weekOut = FinancialTransaction::whereIn('type', ['money_paid', 'expense_recognized'])->whereDate('transaction_date', '>=', today()->startOfWeek())->get()
            ->groupBy(fn ($t) => $t->entry_account ?: 'Not categorised')->map->sum(fn ($t) => (float) $t->amount)->sortDesc()->take(6);
        $latest = FinancialTransaction::with('recorder')->latest('transaction_date')->latest('id')->take(8)->get();

        // No summary cards here: the charts and the latest entries say it.
        return ['title' => 'Today', 'stats' => [], 'charts' => [
            ['title' => 'Entries per day', 'unit' => 'entries', 'bars' => $this->daily(FinancialTransaction::query(), 'transaction_date', null)],
            ['title' => 'Spending this week by category', 'unit' => 'UGX', 'tone' => 'out', 'horizontal' => true,
                'bars' => $weekOut->map(fn ($value, $name) => ['label' => $name, 'value' => $value])->values()->all()],
        ], 'table' => ['title' => 'Latest entries', 'headers' => ['Date', 'Entry', 'Account', 'Amount', 'Recorded by'],
            'rows' => $latest->map(fn ($t) => [$t->transaction_date->format('d M Y'), $t->description ?: Str::headline($t->type),
                $t->entry_account ?: $t->account, $this->money($t->amount), $t->recorder?->displayName() ?? 'System'])->all()]];
    }

    /** The financial year so far (July–June): income, spending, result, balances owed, ledger check. */
    private function panelAccounting(): array
    {
        $start = today()->month >= 7 ? today()->copy()->month(7)->startOfMonth() : today()->copy()->subYear()->month(7)->startOfMonth();
        $year = FinancialTransaction::whereDate('transaction_date', '>=', $start)->whereDate('transaction_date', '<=', today())->get();
        $isIncome = fn ($t) => ($t->type === 'money_received' && $t->transaction_category !== 'customer_prepayment') || $t->type === 'revenue_recognized';
        $isSpend = fn ($t) => in_array($t->type, ['money_paid', 'expense_recognized'], true);
        $income = $year->filter($isIncome)->sum(fn ($t) => (float) $t->amount);
        $spending = $year->filter($isSpend)->sum(fn ($t) => (float) $t->amount);
        $months = collect(range(0, $start->diffInMonths(today()->startOfMonth())))->map(fn ($i) => $start->copy()->addMonths($i));
        $perMonth = fn ($filter) => $months->map(fn ($month) => ['label' => $month->format('M Y'),
            'value' => $year->filter($filter)->filter(fn ($t) => $t->transaction_date->isSameMonth($month))->sum(fn ($t) => (float) $t->amount)])->all();
        $trial = app(\App\Services\LedgerPosting::class)->trialBalance($year);
        $balanced = round($trial->sum('debit') - $trial->sum('credit'), 2) == 0;
        $owedIn = Receivable::where('status', 'open')->get()->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid);
        $owedOut = Payable::where('status', 'open')->get()->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid);

        return ['title' => 'Financial year from '.$start->format('j M Y'), 'stats' => [
            ['Income this year', $this->money($income)], ['Spending this year', $this->money($spending)],
            ['Net result', $this->money($income - $spending)], ['Owed by customers', $this->money($owedIn)],
            ['Owed to suppliers', $this->money($owedOut)], ['Ledger balanced', $year->isEmpty() ? 'No entries yet' : ($balanced ? 'Yes' : 'No — check Trial Balance')],
        ], 'charts' => [
            ['title' => 'Income per month', 'unit' => 'UGX', 'period' => 'this financial year', 'bars' => $perMonth($isIncome)],
            ['title' => 'Spending per month', 'unit' => 'UGX', 'tone' => 'out', 'period' => 'this financial year', 'bars' => $perMonth($isSpend)],
        ]];
    }

    private function panelCustomers(): array
    {
        $open = Receivable::where('status', 'open')->get();
        $owing = $open->groupBy('customer_name')->map(fn ($rows) => $rows->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid))->sortDesc()->take(6);
        $credit = CustomerPrepayment::get()->sum(fn ($p) => max(0, (float) $p->amount - (float) $p->amount_used));

        return ['title' => 'Customers', 'stats' => [
            ['Owed by customers', $this->money($open->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid))],
            ['Open invoices', number_format($open->count())], ['Customer credit held', $this->money($credit)],
        ], 'charts' => [['title' => 'Largest customer balances', 'unit' => 'UGX', 'horizontal' => true,
            'bars' => $owing->map(fn ($value, $name) => ['label' => $name, 'value' => $value])->values()->all()]]];
    }

    private function panelSuppliers(): array
    {
        $open = Payable::where('status', 'open')->get();
        $owed = $open->groupBy('supplier_name')->map(fn ($rows) => $rows->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid))->sortDesc()->take(6);

        return ['title' => 'Suppliers', 'stats' => [
            ['Owed to suppliers', $this->money($open->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid))],
            ['Unpaid bills', number_format($open->count())],
            ['Overdue bills', number_format($open->filter(fn ($r) => $r->due_date && Carbon::parse($r->due_date)->isPast())->count())],
        ], 'charts' => [['title' => 'Largest supplier balances', 'unit' => 'UGX', 'horizontal' => true,
            'bars' => $owed->map(fn ($value, $name) => ['label' => $name, 'value' => $value])->values()->all()]]];
    }

    private function panelStock(): array
    {
        return ['title' => 'Stock', 'stats' => [
            ['Stores', number_format(InventoryStore::where('is_active', true)->count())],
            ['Items registered', number_format(InventoryItem::where('is_active', true)->count())],
            ['Receipts awaiting confirmation', number_format(InventoryMovement::where('receipt_status', 'pending')->count())],
        ], 'charts' => [['title' => 'Stock movements per day', 'unit' => 'movements',
            'bars' => $this->daily(InventoryMovement::query(), 'movement_date', null)]]];
    }

    private function panelPeople(): array
    {
        $active = Employee::active()->with('department')->get();

        return ['title' => 'People', 'stats' => [
            ['Active staff', number_format($active->count())],
            ['Present today', number_format(EmployeeRecord::where('type', 'attendance')->whereDate('recorded_on', today())->distinct('employee_id')->count('employee_id'))],
            ['HR records awaiting approval', number_format(EmployeeRecord::where('status', 'pending')->count())],
        ], 'charts' => [['title' => 'Staff by department', 'unit' => 'staff', 'horizontal' => true,
            'bars' => $active->groupBy(fn ($e) => $e->department?->name ?? 'No department')->map->count()->sortDesc()
                ->map(fn ($value, $name) => ['label' => $name, 'value' => $value])->values()->all()]]];
    }

    private function panelPayroll(): array
    {
        $runs = PayrollRun::latest('period_end')->take(6)->get();
        $latest = $runs->first();

        return ['title' => 'Payroll', 'stats' => [
            ['Latest payroll', $latest ? $latest->payroll_number : 'None yet'],
            ['Status', $latest ? Str::headline($latest->status) : '—'],
            ['Net pay', $latest ? $this->money($latest->net_total) : '—'],
        ], 'charts' => [['title' => 'Net pay by payroll', 'unit' => 'UGX',
            'bars' => $runs->reverse()->map(fn ($run) => ['label' => $run->payroll_number, 'value' => (float) $run->net_total])->values()->all()]]];
    }

    /** Every farm unit side by side: population or output, deaths, health cases and feed over the last 7 days. */
    private function panelUnits(): array
    {
        $since = today()->subDays(6)->toDateString();
        $rows = [];
        $deaths = (int) MortalityRecord::whereDate('occurred_on', '>=', $since)->get()->sum(fn ($m) => (int) $m->quantity_died + (int) $m->quantity_culled);
        $rows[] = ['Poultry',
            number_format((int) AnimalGroup::where('status', 'active')->sum('current_quantity')).' birds · '.number_format((int) EggCollection::whereDate('collected_on', today())->sum('total_eggs')).' eggs today',
            number_format($deaths),
            number_format(DB::table('health_events')->whereDate('occurred_on', '>=', $since)->count()),
            number_format((float) DB::table('feed_consumptions')->where('status', 'approved')->whereDate('used_on', '>=', $since)->sum('quantity_kg'), 1).' kg',
            route('farm-units.show', 'poultry')];
        foreach (['goats' => 'Goats', 'rabbits' => 'Rabbits', 'dogs' => 'Dogs'] as $unit => $label) {
            $groups = DB::table('livestock_groups')->where('unit_code', $unit)->pluck('id');
            $approved = fn (string $table, string $date) => DB::table($table)->whereIn('livestock_group_id', $groups)->where('verification_status', 'approved')->whereDate($date, '>=', $since);
            $rows[] = [$label,
                number_format(DB::table('livestock_animals')->where('unit_code', $unit)->where('status', 'active')->count()).' animals',
                number_format((int) $approved('livestock_mortality_records', 'occurred_on')->sum('quantity')),
                number_format($approved('livestock_health_records', 'recorded_on')->count()),
                number_format((float) $approved('livestock_feed_records', 'recorded_on')->sum('quantity_kg'), 1).' kg',
                route('farm-units.show', $unit)];
        }
        $garden = DB::table('farm_unit_records')->where('unit_code', 'garden')->where('verification_status', 'approved');
        $rows[] = ['Garden',
            number_format((clone $garden)->where('record_type', 'plot')->distinct('subject')->count('subject')).' plots · '.number_format((clone $garden)->where('record_type', 'harvest')->whereDate('record_date', '>=', $since)->count()).' harvests',
            number_format((clone $garden)->where('record_type', 'loss')->whereDate('record_date', '>=', $since)->count()).' crop losses',
            '—',
            number_format((clone $garden)->where('record_type', 'input_application')->whereDate('record_date', '>=', $since)->count()).' input applications',
            route('farm-units.show', 'garden')];

        return ['title' => 'Farm units', 'stats' => [
            ['Birds', $rows[0][1]],
            ['Goats, rabbits & dogs', number_format(DB::table('livestock_animals')->whereIn('unit_code', ['goats', 'rabbits', 'dogs'])->where('status', 'active')->count()).' animals'],
            ['Deaths & culls, last 7 days', number_format($deaths + collect(array_slice($rows, 1, 3))->sum(fn ($r) => (int) str_replace(',', '', $r[2])))],
            ['Garden', $rows[4][1]],
        ], 'charts' => [], 'units' => $rows];
    }

    private function panelPoultry(): array
    {
        $batches = AnimalGroup::where('status', 'active');
        $deaths = MortalityRecord::whereDate('occurred_on', '>=', today()->subDays(6))->get()->sum(fn ($m) => (int) $m->quantity_died + (int) $m->quantity_culled);

        return ['title' => 'Poultry', 'stats' => [
            ['Active batches', number_format((clone $batches)->count())], ['Birds', number_format((int) (clone $batches)->sum('current_quantity'))],
            ['Eggs collected today', number_format((int) EggCollection::whereDate('collected_on', today())->sum('total_eggs'))],
            ['Deaths & culls, last 7 days', number_format($deaths)],
        ], 'charts' => [['title' => 'Eggs collected per day', 'unit' => 'eggs',
            'bars' => $this->daily(EggCollection::query(), 'collected_on', 'total_eggs')]]];
    }

    private function panelSales(): array
    {
        $sales = Sale::where('is_staff_consumption', false);
        $bars = $this->daily((clone $sales), 'sale_date', 'total');

        return ['title' => 'Sales', 'stats' => [
            ['Sales today', $this->money((clone $sales)->whereDate('sale_date', today())->sum('total'))],
            ['Sales, last 14 days', $this->money(collect($bars)->sum('value'))],
            ['Unpaid sale balances', $this->money((clone $sales)->sum('balance'))],
        ], 'charts' => [['title' => 'Sales per day', 'unit' => 'UGX', 'bars' => $bars]]];
    }

    private function panelApprovals(): array
    {
        $pending = Approval::where('status', 'pending');
        $userId = auth()->id();

        // Mirrors the Decisions tabs: Pending Approvals, Sent to Director, Rejected Requests.
        return ['title' => 'Approvals', 'stats' => [
            ['Waiting for a decision', number_format((clone $pending)->count())],
            ['Past their deadline', number_format((clone $pending)->where('due_at', '<', now())->count())],
            ['Sent to Director', number_format((clone $pending)->whereHas('steps', fn ($s) => $s->where('stage', 1)->where('status', 'approved')->where('decided_by', $userId))->count())],
            ['Rejected, last 30 days', number_format(Approval::where('status', 'rejected')->where('updated_at', '>=', now()->subDays(30))->count())],
        ], 'charts' => [
            ['title' => 'Waiting by type', 'unit' => 'requests', 'horizontal' => true, 'wide' => true,
                'bars' => (clone $pending)->get()->groupBy(fn ($a) => method_exists($a, 'label') ? $a->label() : class_basename((string) $a->approvable_type))
                    ->map(fn ($rows, $label) => ['label' => $label, 'value' => $rows->count()])->sortByDesc('value')->values()->all()],
        ]];
    }

    /** Oversight: decisions waiting, overdue ones, and how much activity the audit trail recorded. */
    private function panelAdministration(): array
    {
        $pending = Approval::where('status', 'pending');

        return ['title' => 'Administration', 'stats' => [
            ['Approvals waiting', number_format((clone $pending)->count())],
            ['Approvals past deadline', number_format((clone $pending)->where('due_at', '<', now())->count())],
            ['Actions recorded today', number_format(AuditLog::whereDate('created_at', today())->count())],
            ['Active user accounts', number_format(User::where('is_active', true)->count())],
        ], 'charts' => [
            ['title' => 'Actions recorded per day', 'unit' => 'actions', 'bars' => $this->daily(AuditLog::query(), 'created_at', null)],
        ]];
    }

    private function panelMessages(): array
    {
        return ['title' => 'Messages', 'stats' => [
            ['Unread messages', number_format(Communication::where('recipient_id', auth()->id())->whereNull('read_at')->count())],
        ], 'charts' => []];
    }

    /** Last 14 days, one bar per day (zero-filled). $sum null counts rows. */
    private function daily($query, string $dateColumn, ?string $sum): array
    {
        $start = today()->subDays(13);
        $rows = $query->whereDate($dateColumn, '>=', $start)->get([$dateColumn, ...($sum ? [$sum] : [])])
            ->groupBy(fn ($row) => Carbon::parse($row->{$dateColumn})->toDateString())
            ->map(fn (Collection $group) => $sum ? $group->sum(fn ($row) => (float) $row->{$sum}) : $group->count());

        return collect(range(0, 13))->map(function ($offset) use ($start, $rows) {
            $day = $start->copy()->addDays($offset);
            return ['label' => $day->format('j M'), 'value' => (float) ($rows[$day->toDateString()] ?? 0)];
        })->all();
    }

    private function money($amount): string
    {
        return 'UGX '.number_format((float) $amount);
    }
}
