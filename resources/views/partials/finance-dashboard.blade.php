{{-- Finance dashboard: header, one filter row, three KPI cards with sparklines, the income vs expenditure trend,
     the top five spending categories, and recent transactions. Needs $f = DashboardController::financeDashboard(). --}}
@php
    // Axis labels: whole millions read "UGX 100M", others keep one decimal.
    $axisMoney = fn ($v) => 'UGX '.(abs($v) >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.').'M' : (abs($v) >= 1e3 ? number_format($v / 1e3).'K' : number_format($v)));
    $money = fn ($v) => 'UGX '.(abs($v) >= 1e9 ? number_format($v / 1e9, 1).'B' : (abs($v) >= 1e6 ? number_format($v / 1e6, 1).'M' : (abs($v) >= 1e3 ? number_format($v / 1e3, 0).'K' : number_format($v))));
    $filters = $f['filters'];
    $trend = $f['trend'];
    $past = $trend->reject(fn ($t) => $t['future'])->values();
    // Sparkline points for a series (0–100 wide, 0–40 high).
    $spark = function (string $key) use ($past) {
        $vals = $past->pluck($key)->all();
        if (count($vals) < 2) $vals = array_pad($vals, 2, $vals[0] ?? 0);
        $min = min($vals); $max = max($vals); $span = ($max - $min) ?: 1;
        $pts = [];
        foreach ($vals as $i => $v) $pts[] = round($i / (count($vals) - 1) * 100, 2).','.round(36 - ($v - $min) / $span * 32, 2);
        return implode(' ', $pts);
    };
    // Trend chart geometry (viewBox 0 0 720 260): nice y ticks over income, expenditure and net.
    $top = max(1, $trend->max(fn ($t) => max($t['income'], $t['expenditure'], $t['net'])));
    $low = min(0, $trend->min('net'));
    $stepRaw = ($top - $low) / 4; $mag = 10 ** floor(log10(max($stepRaw, 1))); $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s >= $stepRaw);
    $yMax = ceil($top / $step) * $step; $yMin = floor($low / $step) * $step;
    $plot = ['x' => 86, 'y' => 12, 'w' => 624, 'h' => 208];
    $yOf = fn ($v) => round($plot['y'] + $plot['h'] - ($v - $yMin) / max(1, $yMax - $yMin) * $plot['h'], 2);
    $n = max(1, $trend->count()); $slot = $plot['w'] / $n; $bar = min(22, max(3, ($slot - 6) / 2 - 1));
    // Pie slices for the top five categories (share of total expenditure), validated palette.
    $pieColours = ['#E3A25A', '#8E2433', '#D9632B', '#C8B04E', '#3E8A55'];
    $cats = $f['categories']->values();
    $catTotal = max(1, $cats->sum('amount'));
    $angle = -90; $slices = [];
    foreach ($cats as $i => $c) {
        $sweep = $c['amount'] / $catTotal * 360; $a0 = deg2rad($angle); $a1 = deg2rad($angle + $sweep);
        $large = $sweep > 180 ? 1 : 0;
        $path = $sweep >= 359.99 ? 'M100,10 A90,90 0 1,1 99.99,10 Z'
            : sprintf('M100,100 L%.2f,%.2f A90,90 0 %d,1 %.2f,%.2f Z', 100 + 90 * cos($a0), 100 + 90 * sin($a0), $large, 100 + 90 * cos($a1), 100 + 90 * sin($a1));
        $slices[] = ['path' => $path, 'colour' => $pieColours[$i], 'cat' => $c];
        $angle += $sweep;
    }
    $changeLine = function ($pct) {
        if ($pct === null) return '<span class="fd-change fd-change--none">No earlier figures</span>';
        $up = $pct >= 0;
        return '<span class="fd-change '.($up ? 'fd-change--up' : 'fd-change--down').'"><svg viewBox="0 0 12 12" aria-hidden="true"><path d="'.($up ? 'M6 1 L11 7 H7.5 V11 H4.5 V7 H1 Z' : 'M6 11 L11 5 H7.5 V1 H4.5 V5 H1 Z').'"/></svg>'.($up ? '+' : '').number_format($pct, 0).'%</span><small>vs previous period</small>';
    };
    $periods = ['this_year' => 'This Year', 'last_year' => 'Last Year', 'this_quarter' => 'This Quarter', 'this_month' => 'This Month', 'last_12' => 'Last 12 Months'];
    $categoryOf = fn ($t) => $t->entry_account ?: (str_starts_with((string) $t->description, 'Sales receipt') ? 'Sales' : (\Illuminate\Support\Str::headline((string) $t->transaction_category) ?: 'Other'));
    $isIncomeRow = fn ($t) => in_array($t->type, ['money_received', 'revenue_recognized'], true) && ! in_array($t->transaction_category, ['capital_and_equity', 'customer_prepayment', 'banking'], true);
@endphp
<section class="fd">
    <header class="fd-head">
        <div class="fd-title">
            <span class="fd-title__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/></svg></span>
            <div><h2>Finance Dashboard</h2><p>Overview of your farm's financial performance</p></div>
        </div>
        <div class="fd-head__actions">
            <a class="fd-btn fd-btn--primary" href="{{ route('finance.transactions.create') }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Finance Posting</a>
            <button type="button" class="fd-btn fd-btn--ghost" onclick="window.brooksExportPage && window.brooksExportPage()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 19h14"/></svg>Export to Excel</button>
        </div>
    </header>

    <form method="GET" class="fd-card fd-filters" action="{{ route('dashboard') }}">
        <div class="fd-field"><label for="fd-period">Period</label><select id="fd-period" name="period" onchange="this.form.submit()">@foreach($periods as $key => $label)<option value="{{ $key }}" @selected($filters['period'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="fd-field"><label for="fd-view">View by</label><select id="fd-view" name="view" onchange="this.form.submit()">@foreach(['monthly' => 'Monthly', 'weekly' => 'Weekly', 'daily' => 'Daily'] as $key => $label)<option value="{{ $key }}" @selected($filters['view'] === $key)>{{ $label }}</option>@endforeach</select></div>
        <div class="fd-field"><label for="fd-day">Daily</label><input id="fd-day" type="date" name="day" value="{{ $filters['day'] }}" max="{{ now()->toDateString() }}" onchange="this.form.submit()"></div>
        <div class="fd-field"><label for="fd-quarter">Quarter</label><select id="fd-quarter" name="quarter" onchange="this.form.submit()"><option value="">All</option>@foreach([1, 2, 3, 4] as $q)<option value="{{ $q }}" @selected($filters['quarter'] === $q)>Q{{ $q }}</option>@endforeach</select></div>
        <div class="fd-field"><label for="fd-month">Month</label><select id="fd-month" name="month" onchange="this.form.submit()"><option value="">All</option>@foreach(range(1, 12) as $m)<option value="{{ $m }}" @selected($filters['month'] === $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>@endforeach</select></div>
        <noscript><button class="fd-btn fd-btn--primary">Apply</button></noscript>
    </form>
    <p class="fd-range">{{ $f['start']->format('d M Y') }} – {{ $f['end']->format('d M Y') }}@if($filters['day'] || $filters['quarter'] || $filters['month']) · <a href="{{ route('dashboard', ['period' => $filters['period'], 'view' => $filters['view']]) }}">Clear</a>@endif</p>

    <div class="fd-kpis">
        <article class="fd-card fd-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--in" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 18 18 6M9 6h9v9"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Total Income</span><strong>{{ $money($f['income']) }}</strong>{!! $changeLine($f['incomeChange']) !!}</div>
            <svg class="fd-spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="fdg-in" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2E7D4F" stop-opacity=".28"/><stop offset="1" stop-color="#2E7D4F" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 {{ $spark('income') }} 100,40" fill="url(#fdg-in)"/><polyline points="{{ $spark('income') }}" fill="none" stroke="#2E7D4F" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>
        </article>
        <article class="fd-card fd-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--out" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 9v9H9"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Total Expenditure</span><strong>{{ $money($f['expenditure']) }}</strong>{!! $changeLine($f['expenditureChange']) !!}</div>
            <svg class="fd-spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="fdg-out" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#9B1C2C" stop-opacity=".28"/><stop offset="1" stop-color="#9B1C2C" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 {{ $spark('expenditure') }} 100,40" fill="url(#fdg-out)"/><polyline points="{{ $spark('expenditure') }}" fill="none" stroke="#9B1C2C" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>
        </article>
        <article class="fd-card fd-kpi" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--net" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 20V12M12 20V5M18 20v-9"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Net Cash Movement (Recorded)</span><strong class="{{ $f['net'] < 0 ? 'fd-negative' : '' }}">{{ $money($f['net']) }}</strong>{!! $changeLine($f['netChange']) !!}</div>
            <svg class="fd-spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="fdg-net" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#C98A1E" stop-opacity=".3"/><stop offset="1" stop-color="#C98A1E" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 {{ $spark('net') }} 100,40" fill="url(#fdg-net)"/><polyline points="{{ $spark('net') }}" fill="none" stroke="#C98A1E" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>
        </article>
    </div>

    <div class="fd-charts">
        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4v16h16"/><path d="M7 15l4-4 3 3 5-6"/></svg></span>Income vs Expenditure Trend</h3>
                <span class="fd-pill">{{ $periods[$filters['period']] }}</span>
            </div>
            <div class="fd-legend"><span><i style="background:#2E7D4F"></i>Income</span><span><i style="background:#9B1C2C"></i>Expenditure</span><span><i class="fd-legend__line"></i>Net</span></div>
            <div class="fd-trend" data-fd-trend>
                <svg viewBox="0 0 720 250" role="img" aria-label="Income and expenditure by {{ $filters['view'] === 'monthly' ? 'month' : ($filters['view'] === 'weekly' ? 'week' : 'day') }}">
                    @for($v = $yMin; $v <= $yMax + 0.5; $v += $step)
                        <line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf($v) }}" y2="{{ $yOf($v) }}" class="fd-grid"/>
                        <text x="{{ $plot['x'] - 8 }}" y="{{ $yOf($v) + 4 }}" text-anchor="end" class="fd-axis">{{ $axisMoney($v) }}</text>
                    @endfor
                    @foreach($trend as $i => $t)
                        @php
                            $cx = $plot['x'] + $slot * $i + $slot / 2;
                        @endphp
                        @if($t['income'] > 0)<path d="M{{ $cx - $bar - 1 }},{{ $yOf(0) }} V{{ $yOf($t['income']) + 3 }} q0,-3 3,-3 h{{ $bar - 6 }} q3,0 3,3 V{{ $yOf(0) }} Z" fill="#2E7D4F"/>@endif
                        @if($t['expenditure'] > 0)<path d="M{{ $cx + 1 }},{{ $yOf(0) }} V{{ $yOf($t['expenditure']) + 3 }} q0,-3 3,-3 h{{ $bar - 6 }} q3,0 3,3 V{{ $yOf(0) }} Z" fill="#9B1C2C"/>@endif
                        @if($n <= 31 || $i % (int) ceil($n / 16) === 0)<text x="{{ $cx }}" y="{{ $plot['y'] + $plot['h'] + 22 }}" text-anchor="middle" class="fd-axis">{{ $t['label'] }}</text>@endif
                        <rect x="{{ $cx - $slot / 2 }}" y="{{ $plot['y'] }}" width="{{ $slot }}" height="{{ $plot['h'] }}" fill="transparent" data-tip="{{ $t['label'] }}|{{ number_format($t['income']) }}|{{ number_format($t['expenditure']) }}|{{ number_format($t['net']) }}"/>
                    @endforeach
                    <line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf(0) }}" y2="{{ $yOf(0) }}" class="fd-baseline"/>
                    @php
                        $netPts = $trend->map(fn ($t, $i) => ['x' => $plot['x'] + $slot * $i + $slot / 2, 'y' => $yOf($t['net']), 'future' => $t['future']])->reject(fn ($p) => $p['future'])->values();
                    @endphp
                    @if($netPts->count() > 1)<polyline points="{{ $netPts->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}" fill="none" stroke="#C98A1E" stroke-width="2" stroke-linejoin="round" pointer-events="none"/>@endif
                    @foreach($netPts as $p)<circle cx="{{ $p['x'] }}" cy="{{ $p['y'] }}" r="4" fill="#fff" stroke="#C98A1E" stroke-width="2" pointer-events="none"/>@endforeach
                </svg>
                <div class="fd-tip" hidden></div>
            </div>
        </section>

        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v9h9a9 9 0 1 1-9-9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/></svg></span>Top 5 Spending Categories</h3>
                <span class="fd-pill">{{ $periods[$filters['period']] }}</span>
            </div>
            @if($cats->isEmpty())
                <p class="fd-empty">No spending in this period.</p>
            @else
            <div class="fd-pie">
                <svg viewBox="0 0 200 200" role="img" aria-label="Top spending categories">
                    @foreach($slices as $s)<path d="{{ $s['path'] }}" fill="{{ $s['colour'] }}" stroke="#fff" stroke-width="2" stroke-linejoin="round"><title>{{ $s['cat']['name'] }}: UGX {{ number_format($s['cat']['amount']) }} ({{ number_format($s['cat']['share'], 0) }}%)</title></path>@endforeach
                </svg>
                <ul class="fd-pie__legend">
                    @foreach($slices as $s)<li><i style="background:{{ $s['colour'] }}"></i><span>{{ $s['cat']['name'] }}</span><strong>{{ $money($s['cat']['amount']) }} ({{ number_format($s['cat']['share'], 0) }}%)</strong></li>@endforeach
                </ul>
            </div>
            @endif
        </section>
    </div>

    <section class="fd-card fd-tx">
        <div class="fd-tx__head">
            <h3><span class="fd-chart__icon fd-chart__icon--doc" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg></span>Recent Financial Transactions</h3>
            <form method="GET" class="fd-tx__tools" action="{{ route('dashboard') }}">
                @foreach(['period', 'view', 'day', 'quarter', 'month'] as $keep)@if($filters[$keep])<input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">@endif @endforeach
                <label class="fd-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" name="tq" value="{{ $filters['tq'] }}" placeholder="Search transactions..." aria-label="Search transactions"></label>
                <label class="fd-filter-btn"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"/></svg><select name="ttype" onchange="this.form.submit()" aria-label="Filter by type"><option value="">Filter</option><option value="income" @selected($filters['ttype'] === 'income')>Income</option><option value="expense" @selected($filters['ttype'] === 'expense')>Expense</option></select></label>
                <a class="fd-more" href="{{ route('finance.transactions.index', ['view' => 'register']) }}" title="Open the full register" aria-label="Open the full register">&#8942;</a>
            </form>
        </div>
        <div class="fd-tx__table">
            <table>
                <thead><tr><th>Date &darr;</th><th>Reference</th><th>Description</th><th>Category</th><th>Type</th><th>Amount</th><th></th></tr></thead>
                <tbody>
                @forelse($f['transactions'] as $t)
                    @php
                        $in = $isIncomeRow($t);
                        $capital = $t->type === 'money_received' && ! $in;
                    @endphp
                    <tr>
                        <td>{{ $t->transaction_date->format('d M Y') }}</td>
                        <td>{{ $t->reference ?: '#'.$t->id }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($t->description ?: $t->counterparty, 60) }}</td>
                        <td>{{ $categoryOf($t) }}</td>
                        <td><span class="fd-type {{ $in ? 'fd-type--in' : ($capital ? 'fd-type--other' : 'fd-type--out') }}">{{ $in ? 'Income' : ($capital ? 'Capital' : 'Expense') }}</span></td>
                        <td>UGX {{ number_format($t->amount) }}</td>
                        <td><a class="fd-more" href="{{ route('finance.transactions.index', ['account' => $t->account]) }}" title="Open {{ $t->account }}" aria-label="Open {{ $t->account }}">&#8942;</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="fd-empty">No transactions in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="fd-tx__foot">
            <span>Showing {{ $f['transactions']->firstItem() ?? 0 }} to {{ $f['transactions']->lastItem() ?? 0 }} of {{ number_format($f['transactions']->total()) }} transactions</span>
            <span class="fd-pager">
                <a class="{{ $f['transactions']->onFirstPage() ? 'is-off' : '' }}" href="{{ $f['transactions']->previousPageUrl() ?: '#' }}" aria-label="Previous">&lsaquo;</a>
                <span class="is-current">{{ $f['transactions']->currentPage() }}</span>
                <a class="{{ $f['transactions']->hasMorePages() ? '' : 'is-off' }}" href="{{ $f['transactions']->nextPageUrl() ?: '#' }}" aria-label="Next">&rsaquo;</a>
            </span>
        </div>
    </section>
</section>
<script>
(function () {
    var box = document.querySelector('[data-fd-trend]'); if (!box) return;
    var tip = box.querySelector('.fd-tip');
    box.addEventListener('mousemove', function (e) {
        var t = e.target.closest('[data-tip]'); if (!t) { tip.hidden = true; return; }
        var d = t.dataset.tip.split('|'), r = box.getBoundingClientRect();
        tip.innerHTML = '<strong>' + d[0] + '</strong><span><i style="background:#2E7D4F"></i>Income UGX ' + d[1] + '</span><span><i style="background:#9B1C2C"></i>Expenditure UGX ' + d[2] + '</span><span><i style="background:#C98A1E"></i>Net UGX ' + d[3] + '</span>';
        tip.hidden = false;
        var x = e.clientX - r.left + 14; if (x + tip.offsetWidth > r.width) x = e.clientX - r.left - tip.offsetWidth - 14;
        tip.style.left = Math.max(0, x) + 'px'; tip.style.top = Math.max(0, e.clientY - r.top - 20) + 'px';
    });
    box.addEventListener('mouseleave', function () { tip.hidden = true; });
})();
</script>
