@php
    $p = $data['productionOverview'];
    $units = $p['units']->keyBy('code');
    $chartMax = $p['productionChartMax'];
    $feedMax = max(1, $p['feedPlan']->max(fn ($house) => max($house['recordedKg'], $house['requiredKg'])));
    $unitMax = max(1, $p['units']->where('code', '!=', 'garden')->max('value'));
    $attention = $p['attentionRecords'];
@endphp

@push('styles')
<style>
    .content:has(.production-dashboard){max-width:none;padding:18px 24px 42px;background:#f7f5f1}
    .production-dashboard{--pd-red:#a9262d;--pd-red-dark:#8d1f27;--pd-ink:#111827;--pd-muted:#667085;--pd-line:#eeeae5;display:grid;gap:12px;color:var(--pd-ink);font-family:inherit}
    .production-dashboard *{box-sizing:border-box}
    .pd-head{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:0 6px 2px}
    .pd-head h2{font-size:1.72rem;letter-spacing:-.035em;margin:0 0 3px;color:#101522}
    .pd-head p{margin:0;color:var(--pd-muted);font-size:.95rem}
    .pd-head__actions{display:flex;align-items:center;gap:10px}
    .pd-period{display:flex;align-items:center;gap:8px;padding:9px 12px;border:1px solid #e8e5e1;border-radius:10px;background:#fff;color:#253047;font-size:.82rem}
    .pd-period svg{width:18px;height:18px;color:#202b3c}
    .pd-period select{border:0;background:transparent;color:inherit;font:inherit;outline:0}
    .pd-link{color:var(--pd-red);font-weight:700;font-size:.82rem;text-decoration:none}
    .pd-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .pd-kpi{display:grid;grid-template-columns:58px minmax(0,1fr);gap:13px;align-items:center;min-height:93px;padding:14px 16px;background:#fff;border:1px solid var(--pd-line);border-radius:11px;box-shadow:0 2px 7px #161b2508}
    .pd-kpi__icon{display:grid;place-items:center;width:58px;height:58px;border-radius:12px;background:#fdebec;color:#a9262d}
    .pd-kpi__icon svg{width:30px;height:30px;stroke:currentColor}
    .pd-kpi:nth-child(2) .pd-kpi__icon{color:#268849;background:#edf7ef}.pd-kpi:nth-child(3) .pd-kpi__icon{color:#7041a4;background:#f5eefe}.pd-kpi:nth-child(4) .pd-kpi__icon{color:#1770bd;background:#edf7fe}
    .pd-kpi:nth-child(5) .pd-kpi__icon,.pd-kpi:nth-child(7) .pd-kpi__icon{color:#e87817;background:#fff3e7}.pd-kpi:nth-child(6) .pd-kpi__icon{color:#b52d36;background:#fdebec}.pd-kpi:nth-child(8) .pd-kpi__icon{color:#23884b;background:#eef7ef}
    .pd-kpi__label{display:block;color:#58647c;font-size:.79rem;line-height:1.25}.pd-kpi strong{display:block;font-size:1.42rem;line-height:1.15;letter-spacing:-.035em;margin:3px 0 1px;color:#101522;font-variant-numeric:tabular-nums}.pd-kpi small{font-size:.75rem;color:#5e687c}
    .pd-charts{display:grid;grid-template-columns:1fr 1fr;gap:10px}
    .pd-panel{min-width:0;background:#fff;border:1px solid var(--pd-line);border-radius:11px;padding:14px 16px;box-shadow:0 2px 7px #161b2508}
    .pd-panel--accent{border-left:2px solid #dc5a4f}
    .pd-panel__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:10px}
    .pd-panel__title{display:flex;align-items:center;gap:9px;min-width:0}.pd-panel__title h3{margin:0;font-size:.97rem;letter-spacing:-.02em}.pd-panel__title small{font-size:.78em;font-weight:500}
    .pd-panel__icon{display:grid;place-items:center;width:34px;height:34px;flex:0 0 34px;border-radius:9px;color:var(--pd-red);background:#feeeea}.pd-panel__icon svg{width:21px;height:21px}
    .pd-legend{display:flex;gap:14px;align-items:center;color:#58647c;font-size:.68rem;white-space:nowrap}.pd-legend span{display:flex;align-items:center;gap:5px}.pd-legend i{width:9px;height:9px;border-radius:3px;background:var(--pd-red)}.pd-legend i.soft{background:#eee6dc}
    .pd-chart{display:grid;grid-template-columns:35px minmax(0,1fr);height:150px;gap:6px}
    .pd-axis{display:flex;flex-direction:column;justify-content:space-between;text-align:right;padding:0 0 20px;color:#59647b;font-size:.63rem}
    .pd-chart__plot{position:relative;display:grid;grid-template-columns:repeat(12,minmax(0,1fr));align-items:end;gap:7px;padding:7px 2px 20px;background:repeating-linear-gradient(to bottom,transparent 0,transparent 27px,#eeeae6 28px,#eeeae6 29px);border-bottom:1px solid #e5e1dc}
    .pd-month{height:100%;display:flex;align-items:end;justify-content:center;gap:4px;position:relative}.pd-month i{display:block;width:min(42%,18px);min-height:2px;border-radius:3px 3px 0 0;background:var(--pd-red)}.pd-month i.soft{background:#eee6dc}.pd-month small{position:absolute;top:calc(100% + 3px);font-size:.58rem;color:#59647b;white-space:nowrap}
    .pd-feed-chart{display:grid;gap:8px;padding:3px 0 2px}.pd-feed-row{display:grid;grid-template-columns:minmax(75px,1fr) minmax(100px,2.1fr) auto;gap:10px;align-items:center;font-size:.7rem;color:#3a455d}.pd-feed-row__name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.pd-bars{height:17px;position:relative;border-bottom:1px solid #eeeae6}.pd-bars i{position:absolute;left:0;bottom:0;display:block;height:8px;max-width:100%;background:var(--pd-red);border-radius:3px 3px 0 0}.pd-bars i.soft{bottom:8px;background:#eee6dc;height:6px}.pd-feed-value{min-width:85px;text-align:right;color:#515c72;font-size:.65rem}
    .pd-lower{display:grid;grid-template-columns:.9fr 1fr;gap:10px}
    .pd-table{width:100%;border-collapse:collapse;font-size:.7rem}.pd-table th{padding:7px 8px;color:#725b47;background:#f8f6f3;text-align:left;font-weight:700;white-space:nowrap}.pd-table td{padding:8px;border-bottom:1px solid #f0eeeb;color:#26334c;vertical-align:middle}.pd-table tr:last-child td{border-bottom:0}.pd-table th:first-child,.pd-table td:first-child{width:38px}.pd-table__empty{text-align:center!important;color:#667085!important;padding:18px!important}
    .pd-unit{display:grid;grid-template-columns:minmax(110px,1.15fr) minmax(55px,1fr) minmax(115px,1fr) 65px;gap:8px;align-items:center}.pd-unit__bar{height:8px;border-radius:10px;background:#f3f0ed;overflow:hidden}.pd-unit__bar i{display:block;height:100%;border-radius:inherit;background:linear-gradient(90deg,#a9262d,#d05a51)}.pd-unit strong{text-align:right;font-variant-numeric:tabular-nums}
    .pd-attention{padding:0}.pd-attention .pd-panel__head{padding:12px 14px 0}.pd-attention .pd-table{margin-top:4px}.pd-status{display:inline-block;border-radius:99px;padding:4px 10px;background:#fde7e5;color:#aa1e27;font-weight:700}
    .pd-view-all{font-size:.78rem;color:var(--pd-red);font-weight:700;text-decoration:underline;text-underline-offset:3px}
    .pd-muted{color:#667085;font-size:.76rem;margin:8px 0 2px}
    @media(max-width:1100px){.pd-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.pd-charts,.pd-lower{grid-template-columns:1fr}.pd-unit{grid-template-columns:minmax(110px,1fr) minmax(55px,.7fr) minmax(115px,1fr) 65px}}
    @media(max-width:680px){.content:has(.production-dashboard){padding:14px 12px 30px}.pd-head{align-items:flex-start;flex-direction:column}.pd-head__actions{width:100%;justify-content:space-between}.pd-kpis{gap:7px}.pd-kpi{grid-template-columns:43px minmax(0,1fr);gap:9px;padding:10px;min-height:76px}.pd-kpi__icon{width:43px;height:43px}.pd-kpi__icon svg{width:24px;height:24px}.pd-kpi strong{font-size:1.12rem}.pd-kpi__label{font-size:.67rem}.pd-kpi small{font-size:.65rem}.pd-panel{padding:12px 10px}.pd-panel__head{align-items:flex-start}.pd-legend{font-size:.58rem;gap:7px}.pd-chart__plot{gap:3px}.pd-month small{font-size:.5rem}.pd-unit{grid-template-columns:1fr auto;gap:3px 8px}.pd-unit__bar{grid-column:1/2;grid-row:2}.pd-unit strong{grid-column:2;grid-row:1/3}.pd-table{font-size:.63rem}.pd-table th,.pd-table td{padding:7px 5px}.pd-table--recent{min-width:620px}.pd-panel:has(.pd-table--recent){overflow:auto}}
</style>
@endpush

<section class="production-dashboard" aria-labelledby="production-dashboard-title">
    <header class="pd-head">
        <div><h2 id="production-dashboard-title">Production Dashboard</h2><p>Farm performance across all livestock for the selected period.</p></div>
        <div class="pd-head__actions">
            <span class="pd-period"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/></svg>{{ $p['monthly']->first()['period'] }} – {{ $p['monthly']->last()['period'] }} <span aria-hidden="true">⌄</span></span>
            <a class="pd-link" href="{{ route('operations.reports') }}">Production reports&nbsp; →</a>
        </div>
    </header>

    <div class="pd-kpis">
        @foreach([
            ['poultry','Poultry',$units['poultry']['value'] ?? 0,'birds','paw'],
            ['goats','Goats',$units['goats']['value'] ?? 0,'animals','paw'],
            ['rabbits','Rabbits',$units['rabbits']['value'] ?? 0,'animals','paw'],
            ['dogs','Dogs',$units['dogs']['value'] ?? 0,'animals','paw'],
            ['eggsToday','Eggs collected today',$p['eggsToday'],'eggs','egg'],
            ['eggsMonth','Eggs collected this month',$p['eggsMonth'],'eggs','egg'],
            ['damaged','Damaged eggs this month',$p['damagedMonth'],'eggs','egg'],
            ['feed','Estimated feed today (poultry)',number_format($p['dailyFeedKg'], 2),'kg','sack'],
        ] as [$key,$label,$value,$measure,$icon])
            <article class="pd-kpi">
                <span class="pd-kpi__icon"><svg aria-hidden="true"><use href="#i-{{ $icon }}"></use></svg></span>
                <span><span class="pd-kpi__label">{{ $label }}</span><strong>{{ is_numeric($value) ? number_format((float)$value) : $value }}</strong><small>{{ $measure }}</small></span>
            </article>
        @endforeach
    </div>

    <div class="pd-charts">
        <section class="pd-panel pd-panel--accent">
            <div class="pd-panel__head"><div class="pd-panel__title"><span class="pd-panel__icon"><svg><use href="#i-chart"></use></svg></span><h3>Production by livestock <small>(this month)</small></h3></div><div class="pd-legend"><span><i></i>This month</span><span><i class="soft"></i>Last month</span></div></div>
            <div class="pd-chart" role="img" aria-label="Production totals by livestock for this month and last month">
                <div class="pd-axis"><span>{{ number_format($chartMax) }}</span><span>{{ number_format((int)($chartMax * .66)) }}</span><span>{{ number_format((int)($chartMax * .33)) }}</span><span>0</span></div>
                <div class="pd-chart__plot" style="grid-template-columns:repeat({{ max(1, $p['productionByUnit']->count()) }},minmax(0,1fr))">
                    @foreach($p['productionByUnit'] as $unit)
                        <div class="pd-month" title="{{ $unit['label'] }}: {{ number_format($unit['current'], 1) }} {{ $unit['measure'] }} this month; {{ number_format($unit['previous'], 1) }} last month">
                            <i style="height:{{ max(1, $unit['current'] / $chartMax * 100) }}%"></i><i class="soft" style="height:{{ max(1, $unit['previous'] / $chartMax * 100) }}%"></i><small>{{ $unit['label'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
        <section class="pd-panel pd-panel--accent">
            <div class="pd-panel__head"><div class="pd-panel__title"><span class="pd-panel__icon"><svg><use href="#i-sack"></use></svg></span><h3>Today's poultry feed (kg)</h3></div><div class="pd-legend"><span><i></i>Recorded</span><span><i class="soft"></i>Estimated</span></div></div>
            @if($p['feedPlan']->isNotEmpty())
                <div class="pd-feed-chart" role="img" aria-label="Recorded and estimated feed by active poultry group">
                    @foreach($p['feedPlan']->take(5) as $house)
                        <div class="pd-feed-row"><span class="pd-feed-row__name" title="{{ $house['house'] }}">{{ $house['house'] }}</span><span class="pd-bars"><i style="width:{{ $house['recordedKg'] / $feedMax * 100 }}%"></i><i class="soft" style="width:{{ $house['requiredKg'] / $feedMax * 100 }}%"></i></span><span class="pd-feed-value">{{ number_format($house['recordedKg'],1) }} / {{ number_format($house['requiredKg'],1) }}</span></div>
                    @endforeach
                </div>
                <p class="pd-muted">Recorded / estimated using the farm's feed-per-bird planning rate.</p>
            @else
                <p class="pd-muted">No active poultry groups are available for today's feed estimate.</p>
            @endif
        </section>
    </div>

    <div class="pd-lower">
        <section class="pd-panel pd-panel--accent">
            <div class="pd-panel__head"><div class="pd-panel__title"><span class="pd-panel__icon"><svg><use href="#i-home"></use></svg></span><h3>Top performing units</h3></div><span class="pd-muted">This month</span></div>
            <table class="pd-table"><thead><tr><th>#</th><th>Farm unit / House</th><th>Livestock</th><th>Key metric</th><th>Value</th></tr></thead><tbody>
                @forelse($p['topUnits'] as $index => $unit)
                    <tr><td>{{ $index + 1 }}</td><td>{{ $unit['unit'] }}</td><td>{{ $unit['livestock'] }}</td><td>{{ $unit['metric'] }}</td><td><strong>{{ $unit['value'] }}</strong></td></tr>
                @empty<tr><td class="pd-table__empty" colspan="5">No approved production entries this month.</td></tr>@endforelse
            </tbody></table>
        </section>
        <section class="pd-panel pd-panel--accent">
            <div class="pd-panel__head"><div class="pd-panel__title"><span class="pd-panel__icon"><svg><use href="#i-clipboard"></use></svg></span><h3>Recent production entries</h3></div><a class="pd-view-all" href="{{ route('operations.reports') }}">View all</a></div>
            <table class="pd-table pd-table--recent"><thead><tr><th>Date</th><th>Activity</th><th>Livestock</th><th>Unit</th><th>Quantity</th><th>Recorded by</th></tr></thead><tbody>
                @forelse($p['recent'] as $row)
                    <tr><td>{{ \Illuminate\Support\Carbon::parse($row['date'])->format('d/m/Y') }}</td><td>{{ $row['activity'] }}</td><td>{{ $row['livestock'] }}</td><td>{{ $row['unit'] }}</td><td>{{ $row['quantity'] }}</td><td>{{ $row['recordedBy'] }}</td></tr>
                @empty<tr><td class="pd-table__empty" colspan="6">No recent entries have been recorded.</td></tr>@endforelse
            </tbody></table>
        </section>
    </div>

    <section class="pd-panel pd-panel--accent pd-attention">
        <div class="pd-panel__head"><div class="pd-panel__title"><span class="pd-panel__icon" style="color:#d94a36;background:#fff0e9"><svg><use href="#i-vet"></use></svg></span><h3>Farm units needing attention</h3></div><a class="pd-view-all" href="{{ route('operations.reports') }}">View all</a></div>
        <table class="pd-table"><thead><tr><th>Date</th><th>Farm unit</th><th>Issue</th><th>Livestock</th><th>Status</th></tr></thead><tbody>
            @forelse($attention as $record)
                <tr><td>{{ $record->record_date?->format('d/m/Y') }}</td><td>{{ $record->subject }}</td><td>{{ $record->notes ?: Str::headline($record->record_type) }}</td><td>{{ ucfirst($record->unit_code) }}</td><td><span class="pd-status">Open</span></td></tr>
            @empty<tr><td class="pd-table__empty" colspan="5">No farm unit records are flagged for attention.</td></tr>@endforelse
        </tbody></table>
    </section>
</section>
