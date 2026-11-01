@extends('layouts.app')
@section('title','Financial Reports')
@section('content')
@php
    $fromDate = \Carbon\Carbon::parse($from); $toDate = \Carbon\Carbon::parse($to);
    // Quick ranges (from, to); the one matching the chosen dates is highlighted, otherwise Custom.
    $ranges = [
        'Today' => [today(), today()],
        'Yesterday' => [today()->subDay(), today()->subDay()],
        'This Month' => [today()->startOfMonth(), today()],
        'Last Month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
        'This Year' => [today()->startOfYear(), today()],
    ];
    $activeRange = collect($ranges)->search(fn ($r) => $r[0]->isSameDay($fromDate) && $r[1]->isSameDay($toDate)) ?: 'Custom';
    $period = ['from' => $from, 'to' => $to];
    $sales = fn (array $extra) => route('sales.index', $extra + ['from' => $from, 'to' => $to]);
    // Each card: icon letters, title, description, main link, and shortcut chips [label => link].
    $groups = [
        ['Financial statements', "Key financial reports on the farm's performance.", 3, [
            ['3', 'Three-Statement Model', 'Income statement, balance sheet and cash flow across the selected period.', route('finance.reports.three-statement'), [
                'Income statement' => route('finance.reports.three-statement').'#income-statement', 'Balance sheet' => route('finance.reports.three-statement').'#balance-sheet', 'Cash flow' => route('finance.reports.three-statement').'#cash-flow']],
            ['PL', 'Profit & Loss Statement', 'Revenue, expenditure and the resulting surplus or loss.', route('finance.reports.statement', ['report' => 'profit-loss'] + $period), [
                'P&L' => route('finance.reports.statement', ['report' => 'profit-loss'] + $period), 'Monthly breakdown' => route('finance.reports.profitability', $period), 'Year-to-date' => route('finance.reports.statement', ['report' => 'profit-loss', 'from' => today()->startOfYear()->toDateString(), 'to' => today()->toDateString()])]],
            ['BS', 'Balance Sheet', "Assets, liabilities and the farm's recorded equity.", route('finance.reports.statement', ['report' => 'balance-sheet'] + $period), [
                'Assets' => route('finance.reports.statement', ['report' => 'balance-sheet'] + $period).'#assets', 'Liabilities' => route('finance.reports.statement', ['report' => 'balance-sheet'] + $period).'#liabilities', 'Equity' => route('finance.reports.statement', ['report' => 'balance-sheet'] + $period).'#equity']],
        ]],
        ['Cash and ledger reports', 'Reports to help you understand cash movement and account balances.', 2, [
            ['CF', 'Cash Flow Statement', 'Where cash came from and how it was used.', route('finance.reports.statement', ['report' => 'cash-flow'] + $period), [
                'Operating' => route('finance.reports.statement', ['report' => 'cash-flow'] + $period), 'Investing' => route('finance.reports.statement', ['report' => 'cash-flow'] + $period), 'Financing' => route('finance.reports.statement', ['report' => 'cash-flow'] + $period)]],
            ['TB', 'Trial Balance', 'Debit and credit balances used to check the ledger.', route('finance.reports.statement', ['report' => 'trial-balance'] + $period), [
                'All accounts' => route('finance.reports.statement', ['report' => 'trial-balance'] + $period), 'Monthly' => route('finance.reports.statement', ['report' => 'trial-balance', 'from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()]), 'Year-to-date' => route('finance.reports.statement', ['report' => 'trial-balance', 'from' => today()->startOfYear()->toDateString(), 'to' => today()->toDateString()])]],
        ]],
        ['Sales and revenue reporting', 'Reports on sales performance and revenue trends.', 2, [
            ['SR', 'Sales Report', 'Sales totals, frequent customers, best-selling products and six-month performance.', $sales(['screen' => 'reports']), [
                'Total sales' => $sales(['screen' => 'reports']), 'Top customers' => $sales(['screen' => 'reports']).'#customers', 'Top products' => $sales(['screen' => 'reports']).'#products']],
            ['SA', 'Sales Analysis', 'Customer, product amount, product quantity and customer month-on-month analysis.', $sales(['screen' => 'analysis']), [
                'By customer' => $sales(['screen' => 'analysis', 'analysis_view' => 'summary']), 'By product' => $sales(['screen' => 'analysis', 'analysis_view' => 'product_amount']), 'Monthly trend' => $sales(['screen' => 'analysis', 'analysis_view' => 'monthly'])]],
            ['RA', 'Revenue Analysis', 'Monthly product-category revenue, operating revenue totals and financial-year variance.', $sales(['screen' => 'analysis', 'analysis_view' => 'revenue']), [
                'By product' => $sales(['screen' => 'analysis', 'analysis_view' => 'revenue']), 'By customer' => $sales(['screen' => 'analysis', 'analysis_view' => 'summary']), 'Monthly trend' => $sales(['screen' => 'analysis', 'analysis_view' => 'monthly'])]],
        ]],
        ['Expense and cost analysis', 'Reports to understand where money is being spent.', 2, [
            ['EA', 'Expenditure Analysis', 'Monthly operating expenditure and financial-year comparisons by account.', route('finance.reports.expenditure-analysis'), [
                'By category' => route('finance.reports.expenditure-analysis'), 'By account' => route('finance.reports.expenditure-analysis').'#accounts', 'Monthly trend' => route('finance.reports.expenditure-analysis').'#monthly']],
            ['UC', 'Unit Costs', 'Cost per unit of production (e.g. per egg, per kg).', route('finance.reports.unit-costs', $period), [
                'Egg production' => route('finance.reports.unit-costs', $period), 'Feed costs' => route('finance.reports.unit-costs', $period).'#feed', 'Other activities' => route('finance.reports.unit-costs', $period).'#other']],
        ]],
    ];
@endphp
<section class="rc" data-keep-link>
    <header class="rc-head">
        <div class="rc-title">
            <span class="rc-title__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 2h9l5 5v15H6z" fill="#9B1C2C"/><path d="M15 2v5h5" fill="#c9505c"/><path d="M9 12h8M9 15.5h8M9 19h5" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/></svg></span>
            <div><h2>Reports Centre</h2><p>Generate and view financial and operational reports.</p></div>
        </div>
        <a class="rc-btn rc-btn--ghost" href="{{ route('finance.reports.export', ['report' => 'overview', 'from' => $from, 'to' => $to]) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 20V12M10 20V6M15 20v-9M20 20V9"/></svg>Export overview</a>
    </header>

    <form method="GET" class="rc-card rc-filter" id="rc-filter">
        <div class="rc-field"><label for="rc-from">From</label><input id="rc-from" type="date" name="from" value="{{ $from }}" required></div>
        <div class="rc-field"><label for="rc-to">To</label><input id="rc-to" type="date" name="to" value="{{ $to }}" required></div>
        <div class="rc-quick">
            <span class="rc-quick__label">Quick ranges</span>
            <div class="rc-quick__list">
                @foreach($ranges as $label => [$a, $b])<button type="button" class="rc-chip-btn {{ $activeRange === $label ? 'is-on' : '' }}" data-from="{{ $a->toDateString() }}" data-to="{{ $b->toDateString() }}">{{ $label }}</button>@endforeach
                <button type="button" class="rc-chip-btn {{ $activeRange === 'Custom' ? 'is-on' : '' }}" data-custom>Custom</button>
            </div>
        </div>
        <button class="rc-btn rc-btn--primary rc-run"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4l13 8-13 8z"/></svg>Run report</button>
    </form>

    @foreach($groups as [$title, $subtitle, $columns, $cards])
    <section class="rc-group">
        <h3>{{ $title }}</h3>
        <p>{{ $subtitle }}</p>
        <div class="rc-grid rc-grid--{{ $columns }}">
            @foreach($cards as [$icon, $name, $description, $link, $chips])
            <article class="rc-card rc-tile">
                <a class="rc-tile__main" href="{{ $link }}">
                    <span class="rc-tile__icon">{{ $icon }}</span>
                    <span class="rc-tile__text"><strong>{{ $name }}</strong><small>{{ $description }}</small></span>
                    <b class="rc-tile__go" aria-hidden="true">&rarr;</b>
                </a>
                <div class="rc-tile__chips">@foreach($chips as $chip => $href)<a href="{{ $href }}">{{ $chip }}</a>@endforeach</div>
            </article>
            @endforeach
        </div>
    </section>
    @endforeach

    @if($farmPerformance)@include('partials.farm-performance', ['e' => $farmPerformance, 'reportsLink' => false])@endif
</section>
<script>
(function () {
    var form = document.getElementById('rc-filter'); if (!form) return;
    var from = document.getElementById('rc-from'), to = document.getElementById('rc-to');
    form.querySelectorAll('.rc-chip-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            form.querySelectorAll('.rc-chip-btn').forEach(function (x) { x.classList.remove('is-on'); });
            b.classList.add('is-on');
            if (b.hasAttribute('data-custom')) { from.focus(); return; }
            from.value = b.dataset.from; to.value = b.dataset.to; form.submit();
        });
    });
})();
</script>
@endsection
