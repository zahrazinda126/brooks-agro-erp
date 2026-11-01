{{--
    Analytics dashboard (App\Services\AnalyticsDashboard): filters · KPI tiles vs last year ·
    monthly trend (this year vs last year, with a separate % strip — one scale per chart) ·
    status ring · two Top-5 bar charts. Widgets the person may not see are already removed.
--}}
@php
    $fmt = function ($v, $format, $compact = true) {
        if ($format === 'pct') return number_format((float) $v, 1).'%';
        $abs = abs((float) $v);
        $num = $compact && $abs >= 1e9 ? number_format($v / 1e9, 1).'B' : ($compact && $abs >= 1e6 ? number_format($v / 1e6, 1).'M' : ($compact && $abs >= 1e4 ? number_format($v / 1e3, 0).'K' : number_format((float) $v)));
        return ($format === 'money' ? 'UGX ' : '').$num;
    };
    $keep = collect(request()->query())->except(['dash_year', 'dash_month', 'dash_quarter'])->all();
@endphp
<section class="dash" aria-label="{{ $dash['title'] }} dashboard">
    <header class="dash__head">
        <div><h2>{{ $dash['title'] }}</h2><p>{{ $period['label'] }}</p></div>
        <form method="GET" class="dash__filters">
            @foreach($keep as $k => $v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
            <label>Period<select name="dash_year" onchange="this.form.dash_month.value=''; this.form.dash_quarter.value=''; this.form.submit()"><option value="">Rolling 12 months</option>@foreach($period['years'] as $y)<option value="{{ $y }}" @selected($period['year'] === $y)>{{ $y }}</option>@endforeach</select></label>
            <label>Quarter<select name="dash_quarter" onchange="this.form.dash_month.value=''; this.form.submit()"><option value="">All</option>@foreach(array_reverse($period['quarters']) as $q)<option value="{{ $q['key'] }}" @selected($period['quarter'] === $q['key'])>{{ $q['label'] }}</option>@endforeach</select></label>
            <label>Month<select name="dash_month" onchange="this.form.dash_quarter.value=''; this.form.submit()"><option value="">All</option>@foreach($period['months']->reverse() as $m)<option value="{{ $m->format('Y-m') }}" @selected($period['month'] === $m->format('Y-m'))>{{ $m->format('M Y') }}</option>@endforeach</select></label>
        </form>
    </header>

    <div class="dash__top">
        @if($dash['kpis'])
        <div class="dash__kpis">
            @foreach($dash['kpis'] as $k)
                <article class="dash-card dash-kpi">
                    <h3 class="dash-card__title">{{ $k['label'] }}@if($k['info'])<span class="dash-info" tabindex="0" title="{{ $k['info'] }}" aria-label="{{ $k['info'] }}">i</span>@endif</h3>
                    <strong class="dash-kpi__value" title="{{ $fmt($k['value'], $k['format'], false) }}">{{ $fmt($k['value'], $k['format']) }}</strong>
                    @if($k['note'] ?? null)<span class="dash-kpi__note">{{ $k['note'] }}</span>@endif
                    @if($k['change'] !== null)
                        <span class="dash-kpi__change {{ $k['good'] ? 'is-good' : 'is-bad' }}">{{ $k['change'] >= 0 ? '▲' : '▼' }} {{ number_format(abs($k['change']), 1) }}% vs last year</span>
                    @elseif(! ($k['note'] ?? null))
                        <span class="dash-kpi__change">{{ $k['format'] === 'money' && $k['label'] === 'Cash & bank position' ? 'To date' : 'No figure for last year yet' }}</span>
                    @endif
                </article>
            @endforeach
        </div>
        @endif

        @if($dash['trend'])
        @php
            $t = $dash['trend']; $ms = $t['months']; $n = max(1, count($ms));
            $max = max(1, collect($ms)->flatMap(fn ($m) => [$m['this'], $m['last']])->max());
            $W = 760; $H = 230; $padL = 56; $padB = 26; $padT = 10; $plotW = $W - $padL - 8; $plotH = $H - $padB - $padT;
            $slot = $plotW / $n; $barW = max(4, min(22, $slot * 0.32));
            $y = fn ($v) => $padT + $plotH - ($v / $max) * $plotH;
            $ticks = collect(range(0, 4))->map(fn ($i) => $max * $i / 4);
            $pcts = collect($ms)->pluck('pct')->filter(fn ($v) => $v !== null);
        @endphp
        <article class="dash-card dash-trend">
            <h3 class="dash-card__title">{{ $t['title'] }}<span class="dash-info" tabindex="0" title="{{ $t['info'] }}" aria-label="{{ $t['info'] }}">i</span></h3>
            @if(collect($ms)->every(fn ($m) => $m['this'] == 0 && $m['last'] == 0))
            <p class="dash-empty">Nothing recorded for this period yet.</p>
            @else
            <div class="dash-legend"><span><i style="background:var(--chart-2)"></i>Last year</span><span><i style="background:var(--chart-1)"></i>This year</span></div>
            <svg viewBox="0 0 {{ $W }} {{ $H }}" class="dash-svg" role="img" aria-label="{{ $t['title'] }}">
                @foreach($ticks as $tick)
                    <line x1="{{ $padL }}" x2="{{ $W - 8 }}" y1="{{ $y($tick) }}" y2="{{ $y($tick) }}" class="dash-grid"/>
                    <text x="{{ $padL - 8 }}" y="{{ $y($tick) + 4 }}" text-anchor="end" class="dash-axis">{{ $fmt($tick, $t['format'] === 'money' ? 'plain' : $t['format']) }}</text>
                @endforeach
                @foreach($ms as $i => $m)
                    @php $cx = $padL + $slot * $i + $slot / 2; @endphp
                    <g class="dash-bar-group">
                        <title>{{ \Carbon\Carbon::parse($m['key'].'-01')->format('M Y') }} — this year {{ $fmt($m['this'], $t['format'], false) }}, last year {{ $fmt($m['last'], $t['format'], false) }}</title>
                        <rect x="{{ $cx - $barW - 1 }}" y="{{ $y($m['last']) }}" width="{{ $barW }}" height="{{ max(0, $padT + $plotH - $y($m['last'])) }}" rx="3" style="fill:var(--chart-2)"/>
                        <rect x="{{ $cx + 1 }}" y="{{ $y($m['this']) }}" width="{{ $barW }}" height="{{ max(0, $padT + $plotH - $y($m['this'])) }}" rx="3" style="fill:var(--chart-1)"/>
                        <rect x="{{ $cx - $slot / 2 }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent"/>
                    </g>
                    <text x="{{ $cx }}" y="{{ $H - 8 }}" text-anchor="middle" class="dash-axis">{{ $m['label'] }}</text>
                @endforeach
            </svg>
            @if($t['pctLabel'])
                @php
                    $pMin = min(0, $pcts->min() ?? 0); $pMax = max(10, $pcts->max() ?? 10); $PH = 70;
                    $py = fn ($v) => 8 + ($PH - 16) - (($v - $pMin) / max(1, $pMax - $pMin)) * ($PH - 16);
                    $points = collect($ms)->map(fn ($m, $i) => $m['pct'] === null ? null : [($padL + $slot * $i + $slot / 2), $py($m['pct']), $m])->filter()->values();
                @endphp
                <div class="dash-legend dash-legend--strip"><span><i style="background:var(--chart-4)"></i>{{ $t['pctLabel'] }}</span></div>
                <svg viewBox="0 0 {{ $W }} {{ $PH }}" class="dash-svg dash-svg--strip" role="img" aria-label="{{ $t['pctLabel'] }}">
                    <line x1="{{ $padL }}" x2="{{ $W - 8 }}" y1="{{ $py(0) }}" y2="{{ $py(0) }}" class="dash-grid"/>
                    <text x="{{ $padL - 8 }}" y="{{ $py($pMax) + 4 }}" text-anchor="end" class="dash-axis">{{ round($pMax) }}%</text>
                    <text x="{{ $padL - 8 }}" y="{{ $py(0) + 4 }}" text-anchor="end" class="dash-axis">0%</text>
                    @if($points->count() > 1)<polyline fill="none" style="stroke:var(--chart-4)" stroke-width="2" points="{{ $points->map(fn ($pt) => round($pt[0], 1).','.round($pt[1], 1))->join(' ') }}"/>@endif
                    @foreach($points as $pt)<circle cx="{{ $pt[0] }}" cy="{{ $pt[1] }}" r="4" style="fill:var(--chart-4)" stroke="var(--card, #fff)" stroke-width="2"><title>{{ \Carbon\Carbon::parse($pt[2]['key'].'-01')->format('M Y') }}: {{ number_format($pt[2]['pct'], 1) }}%</title></circle>@endforeach
                    @if($points->isEmpty())<text x="{{ $W / 2 }}" y="{{ $PH / 2 + 4 }}" text-anchor="middle" class="dash-axis">No figures yet</text>@endif
                </svg>
            @endif
            @endif
        </article>
        @endif
    </div>

    <div class="dash__bottom">
        @if($dash['ring'])
        @php $r = $dash['ring']; $C = 2 * M_PI * 42; $offset = 0; @endphp
        <article class="dash-card dash-ring">
            <h3 class="dash-card__title">{{ $r['title'] }}@if($r['info'])<span class="dash-info" tabindex="0" title="{{ $r['info'] }}" aria-label="{{ $r['info'] }}">i</span>@endif</h3>
            @if($r['total'] > 0)
                <div class="dash-legend">@foreach($r['parts'] as $part)<span><i style="background:{{ $part['color'] }}"></i>{{ $part['label'] }}</span>@endforeach</div>
                <div class="dash-ring__body">
                    <svg viewBox="0 0 120 120" class="dash-ring__svg" role="img" aria-label="{{ $r['title'] }}">
                        @foreach($r['parts'] as $part)
                            @php $len = $C * $part['value'] / $r['total']; @endphp
                            <circle cx="60" cy="60" r="42" fill="none" stroke-width="18" style="stroke:{{ $part['color'] }}" stroke-dasharray="{{ max(0, $len - 1.5) }} {{ $C - max(0, $len - 1.5) }}" stroke-dashoffset="{{ -$offset }}" transform="rotate(-90 60 60)"><title>{{ $part['label'] }}: {{ number_format($part['value']) }} ({{ $part['share'] }}%)</title></circle>
                            @php $offset += $len; @endphp
                        @endforeach
                    </svg>
                    <ul class="dash-ring__list">@foreach($r['parts'] as $part)<li><i style="background:{{ $part['color'] }}"></i>{{ $part['label'] }} <strong>{{ $part['share'] }}%</strong></li>@endforeach</ul>
                </div>
            @else
                <p class="dash-empty">Nothing recorded for this period yet.</p>
            @endif
        </article>
        @endif

        @foreach(['topA', 'topB'] as $key)
            @if($dash[$key])
            @php $b = $dash[$key]; @endphp
            <article class="dash-card dash-top">
                <h3 class="dash-card__title">{{ $b['title'] }}@if($b['info'])<span class="dash-info" tabindex="0" title="{{ $b['info'] }}" aria-label="{{ $b['info'] }}">i</span>@endif</h3>
                @forelse($b['rows'] as $row)
                    <div class="dash-top__row" title="{{ $row['label'] }}: {{ $fmt($row['value'], $b['format'], false) }}">
                        <span class="dash-top__label">{{ $row['label'] }}</span>
                        <span class="dash-top__track"><span class="dash-top__bar" style="width:{{ $b['max'] > 0 ? max(2, round($row['value'] / $b['max'] * 100)) : 0 }}%;background:{{ $b['color'] }}"></span><span class="dash-top__value">{{ $fmt($row['value'], $b['format']) }}</span></span>
                    </div>
                @empty
                    <p class="dash-empty">Nothing recorded for this period yet.</p>
                @endforelse
            </article>
            @endif
        @endforeach
    </div>
</section>
