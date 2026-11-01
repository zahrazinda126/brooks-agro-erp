@extends('layouts.app')
@section('title', 'Finance Posting')
@section('content')
@php
    $money = fn ($v) => 'UGX '.number_format($v);
    $axis = fn ($v) => 'UGX '.($v >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.').'M' : ($v >= 1e3 ? number_format($v / 1e3).'K' : number_format($v)));
    $past = $months->reject(fn ($m, $i) => $year === now()->year && $i + 1 > now()->month)->values();
    $spark = function (string $key) use ($past) {
        $vals = $past->pluck($key)->all();
        if (count($vals) < 2) $vals = array_pad($vals, 2, $vals[0] ?? 0);
        $min = min($vals); $max = max($vals); $span = ($max - $min) ?: 1; $pts = [];
        foreach ($vals as $i => $v) $pts[] = round($i / (count($vals) - 1) * 100, 2).','.round(36 - ($v - $min) / $span * 32, 2);
        return implode(' ', $pts);
    };
    $netPoints = (function () use ($past) {
        $vals = $past->map(fn ($m) => $m['income'] - $m['spending'])->all();
        if (count($vals) < 2) $vals = array_pad($vals, 2, $vals[0] ?? 0);
        $min = min($vals); $max = max($vals); $span = ($max - $min) ?: 1; $pts = [];
        foreach ($vals as $i => $v) $pts[] = round($i / (count($vals) - 1) * 100, 2).','.round(36 - ($v - $min) / $span * 32, 2);
        return implode(' ', $pts);
    })();
    $change = function ($pct, bool $goodWhenUp = true) {
        if ($pct === null) return '<span class="fd-change fd-change--none">&mdash;</span><small>vs previous year</small>';
        $up = $pct >= 0; $good = $up === $goodWhenUp;
        return '<span class="fd-change '.($good ? 'fd-change--up' : 'fd-change--down').'"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="'.($up ? 'M6 1 L11 7 H7.5 V11 H4.5 V7 H1 Z' : 'M6 11 L11 5 H7.5 V1 H4.5 V5 H1 Z').'"/></svg>'.($up ? '+' : '').number_format($pct, 0).'%</span><small>vs previous year</small>';
    };
    $chartTop = max(4000000, $months->max(fn ($m) => max($m['income'], $m['spending'])));
    $mag = 10 ** floor(log10($chartTop / 4)); $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s * 4 >= $chartTop);
    $yMax = $step * 4;
    $plot = ['x' => 72, 'y' => 10, 'w' => 500, 'h' => 150];
    $yOf = fn ($v) => round($plot['y'] + $plot['h'] - $v / $yMax * $plot['h'], 2);
    $slot = $plot['w'] / 12; $bw = min(26, $slot * .62);
    $sparkCard = fn ($id, $colour, $points) => '<svg class="fd-spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$colour.'" stop-opacity=".28"/><stop offset="1" stop-color="'.$colour.'" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 '.$points.' 100,40" fill="url(#'.$id.')"/><polyline points="'.$points.'" fill="none" stroke="'.$colour.'" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
    $isIncomeRow = fn ($t) => in_array($t->type, ['money_received', 'revenue_recognized'], true) && ! in_array($t->transaction_category, ['capital_and_equity', 'customer_prepayment', 'banking'], true);
    $typeOf = fn ($t) => $t->type === 'transfer' ? ['Transfer', 'other'] : ($t->transaction_category === 'capital_and_equity' ? ['Capital', 'other'] : ($isIncomeRow($t) ? ['Income', 'in'] : ($t->type === 'money_paid' ? ['Expense', 'out'] : [\Illuminate\Support\Str::headline($t->type), 'other'])));
    $yearSelect = function () use ($isLastYear) {
        return '<form method="GET"><select class="fd-pill" name="year" onchange="this.form.submit()" aria-label="Year"><option value="">This Year</option><option value="last"'.($isLastYear ? ' selected' : '').'>Last Year</option></select></form>';
    };
    $bars = function (string $key, string $colour) use ($months, $plot, $yOf, $slot, $bw, $money) {
        $out = '';
        foreach ($months as $i => $m) {
            $x = $plot['x'] + $slot * $i + ($slot - $bw) / 2;
            if ($m[$key] > 0) $out .= sprintf('<path d="M%.2f,%.2f V%.2f q0,-3 3,-3 h%.2f q3,0 3,3 V%.2f Z" fill="%s"><title>%s: %s</title></path>', $x, $yOf(0), $yOf($m[$key]) + 3, $bw - 6, $yOf(0), $colour, $m['label'], $money($m[$key]));
            $out .= sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" class="fd-axis">%s</text>', $x + $bw / 2, $plot['y'] + $plot['h'] + 20, $m['label']);
        }
        return $out;
    };
@endphp
<section class="fd fp" data-keep-link data-section-overview="finance-posting">
    <header class="fd-head">
        <div class="fd-title">
            <span class="fd-title__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
            <div><h2>Finance Posting</h2><p>Overview of income, spending and outstanding balances</p></div>
        </div>
    </header>

    <div class="fp-kpis">
        <article class="fd-card fd-kpi fp-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--in" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Income {{ $isLastYear ? 'last year' : 'this year' }}</span><strong>{{ $money($income) }}</strong>{!! $change($incomeChange) !!}</div>
            {!! $sparkCard('fpg-in', '#2E7D4F', $spark('income')) !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--out" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 9v9H9"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Spending {{ $isLastYear ? 'last year' : 'this year' }}</span><strong>{{ $money($spending) }}</strong>{!! $change($spendingChange, false) !!}</div>
            {!! $sparkCard('fpg-out', '#9B1C2C', $spark('spending')) !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--net" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 20V12M12 20V5M18 20v-9"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Net result</span><strong class="{{ $net < 0 ? 'fd-negative' : '' }}">{{ $money($net) }}</strong>{!! $change($netChange) !!}</div>
            {!! $sparkCard('fpg-net', '#9B1C2C', $netPoints) !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi" data-stat>
            <i class="fd-kpi__icon fp-ic--blue" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Owed by customers</span><strong>{{ $money($owedByCustomers) }}</strong><span class="fd-change fd-change--none">{{ $owedByCustomers > 0 ? 'Open invoices' : '0%' }}</span><small>still to collect</small></div>
        </article>
        <article class="fd-card fd-kpi fp-kpi" data-stat>
            <i class="fd-kpi__icon fp-ic--orange" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M2 7h11v9H2zM13 10h4l3 3v3h-7z"/><circle cx="6" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Owed to suppliers</span><strong>{{ $money($owedToSuppliers) }}</strong><span class="fd-change fd-change--none">Open bills</span><small>still to pay</small></div>
        </article>
    </div>

    <article class="fd-card fp-ledger {{ $balanced ? 'fp-ledger--ok' : 'fp-ledger--bad' }}" data-stat>
        <i class="fd-kpi__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v6"/><path d="M6 3v18h7"/><path d="M14 3v4h4"/><circle cx="17" cy="17" r="4"/><path d="m15.4 17 1.2 1.2 2.2-2.4"/></svg></i>
        <div><span data-stat-label>Ledger balanced</span><strong>{{ $balanced ? 'Yes' : 'No' }}</strong><small>{{ $balanced ? 'All postings are balanced' : 'Debits and credits differ by '.$money(abs($difference)) }}</small></div>
    </article>

    <div class="fd-charts fp-charts">
        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M3 17l6-6 4 4 8-8M15 7h6v6"/></svg></span><span>Income per month<small>{{ $isLastYear ? 'Last year' : 'This year' }}</small></span></h3>
                {!! $yearSelect() !!}
            </div>
            <div class="fd-trend"><svg viewBox="0 0 590 190" role="img" aria-label="Income per month">
                @for($v = 0; $v <= $yMax + 0.001; $v += $step)<line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf($v) }}" y2="{{ $yOf($v) }}" class="fd-grid"/><text x="{{ $plot['x'] - 8 }}" y="{{ $yOf($v) + 4 }}" text-anchor="end" class="fd-axis">{{ $axis($v) }}</text>@endfor
                {!! $bars('income', '#2E7D4F') !!}
            </svg></div>
        </section>
        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 20V10M10 20V5M15 20v-7M20 20v-4"/></svg></span><span>Spending per month<small>{{ $isLastYear ? 'Last year' : 'This year' }}</small></span></h3>
                {!! $yearSelect() !!}
            </div>
            <div class="fd-trend"><svg viewBox="0 0 590 190" role="img" aria-label="Spending per month">
                @for($v = 0; $v <= $yMax + 0.001; $v += $step)<line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf($v) }}" y2="{{ $yOf($v) }}" class="fd-grid"/><text x="{{ $plot['x'] - 8 }}" y="{{ $yOf($v) + 4 }}" text-anchor="end" class="fd-axis">{{ $axis($v) }}</text>@endfor
                {!! $bars('spending', '#9B1C2C') !!}
            </svg></div>
        </section>
    </div>

    <section class="fd-card fd-tx">
        <div class="fd-tx__head">
            <h3><span class="fd-chart__icon fd-chart__icon--doc" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg></span>Recent Finance Postings</h3>
            <form method="GET" class="fd-tx__tools">
                @if($isLastYear)<input type="hidden" name="year" value="last">@endif
                <label class="fd-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" name="q" value="{{ $q }}" placeholder="Search postings..." aria-label="Search postings"></label>
                <label class="fd-filter-btn"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"/></svg><select name="type" onchange="this.form.submit()" aria-label="Filter by type"><option value="">Filter</option>@foreach(['income' => 'Income', 'expense' => 'Expense', 'transfer' => 'Transfer', 'capital' => 'Capital'] as $k => $l)<option value="{{ $k }}" @selected($type === $k)>{{ $l }}</option>@endforeach</select></label>
            </form>
        </div>
        <div class="fd-tx__table">
            <table>
                <thead><tr><th>Date &#8597;</th><th>Reference</th><th>Description</th><th>Type</th><th>Module</th><th>Amount (UGX)</th><th>Posted By</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($postings as $t)
                    @php
                        [$typeLabel, $typeClass] = $typeOf($t);
                    @endphp
                    <tr>
                        <td>{{ $t->transaction_date->format('d M Y') }}</td>
                        <td>{{ $t->reference ?: 'FP-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($t->description ?: $t->counterparty, 55) }}</td>
                        <td><span class="fd-type fd-type--{{ $typeClass }}">{{ $typeLabel }}</span></td>
                        <td>{{ \App\Http\Controllers\Finance\FinancePostingController::moduleOf($t) }}</td>
                        <td>{{ number_format($t->amount) }}</td>
                        <td>{{ $t->recorder?->displayName() ?? 'System' }}</td>
                        <td><a class="fd-more" href="{{ route('finance.transactions.index', ['account' => $t->account]) }}" title="Open {{ $t->account }}" aria-label="Open {{ $t->account }}">&#8942;</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="fd-empty">No postings in this year.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="fd-tx__foot">
            <span>Showing {{ $postings->firstItem() ?? 0 }} to {{ $postings->lastItem() ?? 0 }} of {{ number_format($postings->total()) }} postings</span>
            <span class="fd-pager">
                <a class="{{ $postings->onFirstPage() ? 'is-off' : '' }}" href="{{ $postings->previousPageUrl() ?: '#' }}" aria-label="Previous">&lsaquo;</a>
                <span class="is-current">{{ $postings->currentPage() }}</span>
                <a class="{{ $postings->hasMorePages() ? '' : 'is-off' }}" href="{{ $postings->nextPageUrl() ?: '#' }}" aria-label="Next">&rsaquo;</a>
            </span>
        </div>
    </section>
</section>
@endsection
