{{-- Customer Overview: five cards, receivables ageing, balances by status, and customers by outstanding balance.
     Needs $b = ReceivableController::board(). --}}
@php
    $money = fn ($v) => 'UGX '.number_format($v);
    $short = fn ($v) => 'UGX '.($v >= 1e6 ? rtrim(rtrim(number_format($v / 1e6, 1), '0'), '.').'M' : ($v >= 1e3 ? number_format($v / 1e3).'K' : number_format($v)));
    $change = function ($pct, bool $goodWhenUp = true) {
        if ($pct === null) return '<span class="fd-change fd-change--none">New</span><small>vs previous period</small>';
        $up = $pct > 0; $flat = abs($pct) < 0.5; $good = $flat || $up === $goodWhenUp;
        $arrow = $flat ? '' : '<svg viewBox="0 0 12 12" aria-hidden="true"><path d="'.($up ? 'M6 1 L11 7 H7.5 V11 H4.5 V7 H1 Z' : 'M6 11 L11 5 H7.5 V1 H4.5 V5 H1 Z').'"/></svg>';
        return '<span class="fd-change '.($flat ? 'fd-change--none' : ($good ? 'fd-change--up' : 'fd-change--down')).'">'.$arrow.($up ? '+' : '').number_format($pct, 0).'%</span><small>vs previous period</small>';
    };
    $spark = function (array $vals, string $id, string $colour) {
        if (count($vals) < 2) $vals = array_pad($vals, 2, $vals[0] ?? 0);
        $min = min($vals); $max = max($vals); $span = ($max - $min) ?: 1; $pts = [];
        foreach ($vals as $i => $v) $pts[] = round($i / (count($vals) - 1) * 100, 2).','.round(36 - ($v - $min) / $span * 32, 2);
        $p = implode(' ', $pts);
        return '<svg class="fd-spark" viewBox="0 0 100 40" preserveAspectRatio="none" aria-hidden="true"><defs><linearGradient id="'.$id.'" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.$colour.'" stop-opacity=".28"/><stop offset="1" stop-color="'.$colour.'" stop-opacity="0"/></linearGradient></defs><polygon points="0,40 '.$p.' 100,40" fill="url(#'.$id.')"/><polyline points="'.$p.'" fill="none" stroke="'.$colour.'" stroke-width="2" vector-effect="non-scaling-stroke" stroke-linejoin="round"/></svg>';
    };
    $byCount = $b['agingBy'] === 'count';
    $colours = ['#2E7D4F', '#E3A25A', '#E07B2A', '#D4553A', '#9B1C2C'];
    $vals = $b['aging']->pluck($byCount ? 'count' : 'amount');
    $rawTop = max($byCount ? 4 : 4000000, (float) $vals->max());
    $mag = 10 ** floor(log10($rawTop / 4)); $step = collect([1, 2, 2.5, 5, 10])->map(fn ($m) => $m * $mag)->first(fn ($s) => $s * 4 >= $rawTop);
    $yMax = $step * 4;
    $plot = ['x' => 70, 'y' => 10, 'w' => 420, 'h' => 150];
    $yOf = fn ($v) => round($plot['y'] + $plot['h'] - $v / $yMax * $plot['h'], 2);
    $slot = $plot['w'] / 5;
    // Donut of balances by status (share of the total outstanding).
    $total = (float) $b['aging']->sum('amount');
    $arcs = []; $angle = -90;
    foreach ($b['aging'] as $i => $bucket) {
        if ($total <= 0 || $bucket['amount'] <= 0) continue;
        $sweep = $bucket['amount'] / $total * 360;
        if ($sweep >= 359.99) { $arcs[] = ['d' => 'M100,18 A82,82 0 1,1 99.99,18', 'c' => $colours[$i]]; break; }
        $a0 = deg2rad($angle); $a1 = deg2rad($angle + $sweep);
        $arcs[] = ['d' => sprintf('M%.2f,%.2f A82,82 0 %d,1 %.2f,%.2f', 100 + 82 * cos($a0), 100 + 82 * sin($a0), $sweep > 180 ? 1 : 0, 100 + 82 * cos($a1), 100 + 82 * sin($a1)), 'c' => $colours[$i]];
        $angle += $sweep;
    }
    $statusLabel = ['overdue' => 'Overdue', 'due' => 'Due', 'clear' => 'Up to date'];
    $list = $b['list'];
@endphp
<section class="fd cv" data-keep-link data-section-overview="customers">
    <header class="fd-head">
        <div class="fd-title">
            <div><h2>Customers</h2><p>Manage customer records, accounts, payments and outstanding balances.</p></div>
        </div>
        <a class="fd-btn fd-btn--primary" href="{{ route('owner.customers', ['view' => 'add']) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>Add Customer</a>
    </header>

    <div class="fp-kpis cv-kpis">
        <article class="fd-card fd-kpi fp-kpi cv-k1" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--in" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5M14.5 14.2c3.2-.4 6.5 1.4 6.5 4.8"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Total Customers</span><strong>{{ number_format($b['customers']) }}</strong>{!! $change($b['customersChange']) !!}</div>
            {!! $spark($b['trend']['customers'], 'cvg-1', '#2E7D4F') !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi cv-k2" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--out" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4M9 12h6M9 16h6"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Outstanding Invoices</span><strong>{{ number_format($b['openCount']) }}</strong>{!! $change($b['openChange'], false) !!}</div>
            {!! $spark($b['trend']['invoices'], 'cvg-2', '#9B1C2C') !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi cv-k3" data-stat>
            <i class="fd-kpi__icon fp-ic--orange" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M16 15h2"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Amount Owed by Customers</span><strong>{{ $money($b['owed']) }}</strong>{!! $change($b['owedChange'], false) !!}</div>
            {!! $spark($b['trend']['owed'], 'cvg-3', '#E07B2A') !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi cv-k4" data-stat>
            <i class="fd-kpi__icon fp-ic--blue" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="13" rx="2"/><path d="M16 12.5h2M3 9.5h18"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Customer Prepayments</span><strong>{{ $money($b['credit']) }}</strong>{!! $change($b['creditChange']) !!}</div>
            {!! $spark($b['trend']['credit'], 'cvg-4', '#3b6fd4') !!}
        </article>
        <article class="fd-card fd-kpi fp-kpi cv-k5" data-stat>
            <i class="fd-kpi__icon fd-kpi__icon--net" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3l2.6 5.6 6 .7-4.5 4.1 1.2 6L12 16.4 6.7 19.4l1.2-6L3.4 9.3l6-.7z"/></svg></i>
            <div class="fd-kpi__body"><span data-stat-label>Customers on Credit Hold</span><strong>{{ number_format($b['hold']) }}</strong>{!! $change($b['holdChange'], false) !!}</div>
        </article>
    </div>

    <div class="fd-charts rv-charts">
        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 20V10M10 20V5M15 20v-7M20 20v-4"/></svg></span><span class="cv-h">Receivables Aging<small>Total outstanding amount by ageing period</small></span></h3>
                <form method="GET"><select class="fd-pill" name="aging" onchange="this.form.submit()" aria-label="Show by"><option value="amount" @selected(! $byCount)>Amount (UGX)</option><option value="count" @selected($byCount)>Number of invoices</option></select></form>
            </div>
            <div class="fd-trend">
                <svg viewBox="0 0 500 190" role="img" aria-label="Receivables by days past due">
                    @for($v = 0; $v <= $yMax + 0.001; $v += $step)
                        <line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf($v) }}" y2="{{ $yOf($v) }}" class="fd-grid"/>
                        <text x="{{ $plot['x'] - 8 }}" y="{{ $yOf($v) + 4 }}" text-anchor="end" class="fd-axis">{{ $byCount ? number_format($v) : $short($v) }}</text>
                    @endfor
                    @foreach($b['aging'] as $i => $bucket)
                        @php
                            $v = $byCount ? $bucket['count'] : $bucket['amount'];
                            $x = $plot['x'] + $slot * $i + $slot * 0.18; $w = $slot * 0.64;
                        @endphp
                        @if($v > 0)<path d="M{{ $x }},{{ $yOf(0) }} V{{ $yOf($v) + 4 }} q0,-4 4,-4 h{{ $w - 8 }} q4,0 4,4 V{{ $yOf(0) }} Z" fill="{{ $colours[$i] }}"><title>{{ $bucket['label'] }}: {{ $money($bucket['amount']) }} · {{ $bucket['count'] }} {{ \Illuminate\Support\Str::plural('invoice', $bucket['count']) }}</title></path>@endif
                        <text x="{{ $x + $w / 2 }}" y="{{ $plot['y'] + $plot['h'] + 20 }}" text-anchor="middle" class="fd-axis">{{ $bucket['label'] }}</text>
                    @endforeach
                    <line x1="{{ $plot['x'] }}" x2="{{ $plot['x'] + $plot['w'] }}" y1="{{ $yOf(0) }}" y2="{{ $yOf(0) }}" class="fd-baseline"/>
                </svg>
            </div>
        </section>

        <section class="fd-card fd-chart">
            <div class="fd-chart__head">
                <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M12 3v9h9a9 9 0 1 1-9-9z"/><path d="M15 3.5A9 9 0 0 1 20.5 9H15z"/></svg></span><span class="cv-h">Customer Balances by Status<small>Distribution of outstanding balances</small></span></h3>
            </div>
            <div class="cv-donut">
                <svg viewBox="0 0 200 200" role="img" aria-label="Outstanding balances by status">
                    <circle cx="100" cy="100" r="82" fill="none" stroke="#f1ebe4" stroke-width="30"/>
                    @foreach($arcs as $arc)<path d="{{ $arc['d'] }}" fill="none" stroke="{{ $arc['c'] }}" stroke-width="30"/>@endforeach
                    <text x="100" y="92" text-anchor="middle" class="cv-donut__k">UGX</text>
                    <text x="100" y="116" text-anchor="middle" class="cv-donut__v">{{ $total >= 1e6 ? rtrim(rtrim(number_format($total / 1e6, 1), '0'), '.').'M' : number_format($total) }}</text>
                    <text x="100" y="134" text-anchor="middle" class="cv-donut__l">Total Outstanding</text>
                </svg>
                <ul>
                    @foreach($b['aging'] as $i => $bucket)<li><i style="background:{{ $colours[$i] }}"></i><span>{{ $bucket['label'] }}</span><strong>{{ $total > 0 ? number_format($bucket['amount'] / $total * 100, 1) : '0.0' }}%</strong><small>({{ $money($bucket['amount']) }})</small></li>@endforeach
                </ul>
            </div>
        </section>
    </div>

    <section class="fd-card fd-tx">
        <div class="fd-tx__head">
            <h3><span class="fd-chart__icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.4"/><path d="M3 19c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5M14.5 14.2c3.2-.4 6.5 1.4 6.5 4.8"/></svg></span><span class="cv-h">Top Customers by Outstanding Balance<small>Customers with the highest amounts owed</small></span></h3>
            <form method="GET" class="fd-tx__tools">
                @if($byCount)<input type="hidden" name="aging" value="count">@endif
                <label class="fd-search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" name="q" value="{{ $b['q'] }}" placeholder="Search customers..." aria-label="Search customers"></label>
                <label class="fd-filter-btn"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16l-6 7v6l-4 2v-8z"/></svg><select name="status" onchange="this.form.submit()" aria-label="Filter by status"><option value="">Filter</option><option value="overdue" @selected($b['status'] === 'overdue')>Overdue</option><option value="due" @selected($b['status'] === 'due')>Due</option><option value="hold" @selected($b['status'] === 'hold')>On credit hold</option><option value="clear" @selected($b['status'] === 'clear')>Up to date</option></select></label>
                <a class="fd-more" href="{{ route('owner.customers', ['view' => 'accounts']) }}" title="All customer accounts" aria-label="All customer accounts">&#8942;</a>
            </form>
        </div>
        <div class="fd-tx__table">
            <table>
                <thead><tr><th>#</th><th>Customer</th><th>Phone</th><th>Total Invoiced (UGX)</th><th>Total Paid (UGX)</th><th>Outstanding (UGX)</th><th>Last Invoice</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                @forelse($list as $i => $c)
                    <tr>
                        <td>{{ $list->firstItem() + $i }}</td>
                        <td>{{ $c->name }}</td>
                        <td class="u-nowrap">{{ $c->phone ?: '—' }}</td>
                        <td>{{ number_format($c->invoiced) }}</td>
                        <td>{{ number_format($c->paid) }}</td>
                        <td><strong>{{ number_format($c->outstanding) }}</strong></td>
                        <td>{{ $c->last ? \Carbon\Carbon::parse($c->last)->format('d M Y') : '—' }}</td>
                        <td><span class="rv-state rv-state--{{ $c->hold ? 'overdue' : ($c->status === 'clear' ? 'current' : $c->status) }}">{{ $c->hold ? 'On hold' : $statusLabel[$c->status] }}</span></td>
                        <td><a class="fd-more" href="{{ route('customer-accounts.show', $c->id) }}" title="Open {{ $c->name }}'s account" aria-label="Open account">&#8942;</a></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="fd-empty">No customers match.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="fd-tx__foot">
            <span>Showing {{ $list->firstItem() ?? 0 }} to {{ $list->lastItem() ?? 0 }} of {{ number_format($list->total()) }} customers</span>
            <span class="fd-pager">
                <a class="{{ $list->onFirstPage() ? 'is-off' : '' }}" href="{{ $list->previousPageUrl() ?: '#' }}" aria-label="Previous">&lsaquo;</a>
                @foreach($list->getUrlRange(max(1, $list->currentPage() - 1), min($list->lastPage(), max(1, $list->currentPage() - 1) + 2)) as $p => $url)
                    @if($p === $list->currentPage())<span class="is-current">{{ $p }}</span>@else<a href="{{ $url }}">{{ $p }}</a>@endif
                @endforeach
                @if($list->lastPage() > max(1, $list->currentPage() - 1) + 2)<span class="cv-gap">…</span><a href="{{ $list->url($list->lastPage()) }}">{{ $list->lastPage() }}</a>@endif
                <a class="{{ $list->hasMorePages() ? '' : 'is-off' }}" href="{{ $list->nextPageUrl() ?: '#' }}" aria-label="Next">&rsaquo;</a>
            </span>
        </div>
    </section>
</section>
