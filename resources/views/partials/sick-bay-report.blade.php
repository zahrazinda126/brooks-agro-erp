{{-- Sick bay in a unit report (Health & treatment): animals in a sick bay during the period and what became of them. Needs $unit, $from, $to. --}}
@php
    $sickBayRows = \App\Models\SickBayAdmission::with(['sickBay', 'batch', 'house'])->where('unit_code', $unit)
        ->whereDate('admitted_on', '<=', $to)->where(fn ($q) => $q->whereNull('discharged_on')->orWhereDate('discharged_on', '>=', $from))
        ->orderBy('admitted_on')->get();
    $sickBayIn = $sickBayRows->sum('quantity');
    $sickBayRecovered = $sickBayRows->sum('recovered');
@endphp
<section class="card table-wrap u-mt-16">
    <h3>Sick bay</h3>
    <p class="muted">{{ number_format($sickBayIn) }} taken in · {{ number_format($sickBayRecovered) }} recovered{{ $sickBayIn ? ' ('.round($sickBayRecovered / $sickBayIn * 100).'%)' : '' }} · {{ number_format($sickBayRows->sum('died')) }} died · {{ number_format($sickBayRows->sum('culled')) }} culled · {{ number_format($sickBayRows->sum('in_care')) }} still in care</p>
    <table class="exec-table">
        <thead><tr><th>Taken in</th><th>Sick bay</th><th>From</th><th>Condition</th><th class="num">In</th><th class="num">Recovered <small>(back in house)</small></th><th class="num">Died</th><th class="num">Culled</th><th class="num">Still in care</th><th>Group closed</th></tr></thead>
        <tbody>
        @forelse($sickBayRows as $a)
            <tr><td>{{ $a->admitted_on->format('d M Y') }}</td><td>{{ $a->sickBay->name }}</td><td>{{ $a->fromLabel() }}</td><td>{{ $a->condition ?: ($a->signs ? implode(', ', $a->signs) : '—') }}</td><td class="num">{{ number_format($a->quantity) }}</td><td class="num">{{ number_format($a->recovered) }}</td><td class="num">{{ number_format($a->died) }}</td><td class="num">{{ number_format($a->culled) }}</td><td class="num">{{ number_format($a->in_care) }}</td><td>{{ $a->discharged_on?->format('d M Y') ?? 'Still open' }}</td></tr>
        @empty
            <tr><td colspan="10" class="muted">No animals in a sick bay in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>
