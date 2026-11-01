@extends('layouts.app')
@section('title', $name)
@section('content')
{{-- The layout script uses data-section-overview to highlight this section's tab and fill #sectionScreens. --}}
<div class="section-overview" data-section-overview="{{ $section }}">
    <div class="page-head">
        <h2>{{ $name }}</h2>
    </div>

    @foreach($summaries as $summary)
        <section class="overview-panel" aria-label="{{ $summary['title'] }} summary">
            @if($summaries->count() > 1)<p class="card-label">{{ $summary['title'] }}</p>@endif
            @if(! empty($summary['stats']))
            <div class="summary-grid summary-grid--compact">
                @foreach($summary['stats'] as [$label, $value])
                    <x-stat-card :label="$label">{{ $value }}</x-stat-card>
                @endforeach
            </div>
            @endif

            @if(!empty($summary['units']))
            {{-- Every farm unit side by side (Farm Hub production control room) --}}
            <div class="card table-card overview-table">
                <table><caption class="overview-table__title">Units · last 7 days</caption>
                    <thead><tr><th>Unit</th><th>Population / output</th><th class="num">Deaths</th><th class="num">Health cases</th><th class="num">Feed / inputs</th><th></th></tr></thead>
                    <tbody>@foreach($summary['units'] as [$unit, $population, $deaths, $health, $feed, $url])
                        <tr><td><strong>{{ $unit }}</strong></td><td>{{ $population }}</td><td class="num">{{ $deaths }}</td><td class="num">{{ $health }}</td><td class="num">{{ $feed }}</td><td><a class="btn btn--small btn--quiet" href="{{ $url }}" data-keep-link>Open {{ $unit }}</a></td></tr>
                    @endforeach</tbody>
                </table>
            </div>
            @endif

            @if(count($summary['charts']))
            <div class="overview-charts">
                @foreach($summary['charts'] as $chart)
                    @php
                        $max = max(1, collect($chart['bars'])->map(fn ($b) => abs($b['value']))->max() ?: 0);
                        $format = fn ($v) => ($chart['unit'] === 'UGX' ? 'UGX ' : '').number_format($v).($chart['unit'] !== 'UGX' ? ' '.$chart['unit'] : '');
                    @endphp
                    <figure class="overview-chart {{ !empty($chart['horizontal']) ? 'overview-chart--horizontal' : '' }} {{ !empty($chart['wide']) ? 'overview-chart--wide' : '' }} {{ ($chart['tone'] ?? null) === 'out' ? 'overview-chart--out' : '' }}">
                        <figcaption>{{ $chart['title'] }}@if(empty($chart['horizontal'])) <span>{{ $chart['period'] ?? 'last 14 days' }}</span>@endif</figcaption>
                        @if(collect($chart['bars'])->every(fn ($b) => $b['value'] == 0))
                            <p class="overview-chart__empty">Nothing recorded yet.</p>
                        @elseif(!empty($chart['horizontal']))
                            <ul class="hbars">
                                @foreach($chart['bars'] as $bar)
                                    <li><span class="hbars__label">{{ $bar['label'] }}</span><span class="hbars__track"><span class="hbars__bar {{ $bar['value'] < 0 ? 'hbars__bar--negative' : '' }}" style="--size:{{ round(abs($bar['value']) / $max * 100, 1) }}%"></span></span><span class="hbars__value">{{ $format($bar['value']) }}</span></li>
                                @endforeach
                            </ul>
                            @if(!empty($chart['signed']) && collect($chart['bars'])->contains(fn ($b) => $b['value'] < 0))<p class="overview-chart__note">Gold bars are overdrawn (negative) balances.</p>@endif
                        @else
                            <div class="vbars" role="img" aria-label="{{ $chart['title'] }}, {{ $chart['period'] ?? 'last 14 days' }}">
                                @foreach($chart['bars'] as $bar)
                                    <span class="vbars__col" tabindex="0" data-tip="{{ $bar['label'] }}: {{ $format($bar['value']) }}" title="{{ $bar['label'] }}: {{ $format($bar['value']) }}"><span class="vbars__bar" style="--size:{{ round($bar['value'] / $max * 100, 1) }}%"></span></span>
                                @endforeach
                            </div>
                            <div class="vbars__axis"><span>{{ $chart['bars'][0]['label'] }}</span><span>Peak {{ $format($max) }}</span><span>{{ end($chart['bars'])['label'] }}</span></div>
                            <details class="overview-chart__table"><summary>View as table</summary>
                                <table><thead><tr><th>Day</th><th class="num">{{ $chart['unit'] }}</th></tr></thead><tbody>
                                    @foreach($chart['bars'] as $bar)<tr><td>{{ $bar['label'] }}</td><td class="num">{{ number_format($bar['value']) }}</td></tr>@endforeach
                                </tbody></table>
                            </details>
                        @endif
                    </figure>
                @endforeach
            </div>
            @endif

            @if(!empty($summary['table']))
            <div class="card table-card overview-table">
                <table><caption class="overview-table__title">{{ $summary['table']['title'] }}</caption>
                    <thead><tr>@foreach($summary['table']['headers'] as $header)<th>{{ $header }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($summary['table']['rows'] as $row)<tr>@foreach($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($summary['table']['headers']) }}">Nothing recorded yet.</td></tr>@endforelse</tbody>
                </table>
            </div>
            @endif
        </section>
    @endforeach

    @if($summaries->isEmpty())
    {{-- The section's screens are the tabs in the bar above; they are not repeated here. --}}
    <p class="overview-empty">Choose a screen from the tabs above.</p>
    @endif
</div>
@endsection
