@extends('layouts.app')

@section('title', 'Assess Deaths')

@section('content')

@include('partials.health-switch', ['healthCurrent' => 'examination'])
<div class="page-head">
    <h2>Deaths to assess</h2>
    <p>Workers have counted. You decide why.</p>
</div>

@if ($pending->isEmpty())
    <div class="card">
        <p class="card-label">Nothing waiting</p>
        <p class="muted u-m0">
            Every death recorded has been assessed. New ones appear here as
            workers enter them.
        </p>
    </div>
@else
    @foreach ($pending as $r)
        @php
            $waiting = $r->daysWaiting();
        @endphp
        <a class="assess-card {{ $waiting >= 2 ? 'assess-card--late' : '' }}"
           href="{{ route('mortality.assess.edit', $r) }}">

            <div class="assess-top">
                <span class="assess-count">{{ $r->totalLost() }}</span>
                <div>
                    <strong>{{ $r->batch->name ?: $r->batch->code }}</strong>
                    <span class="assess-sub">
                        {{ $r->batch->house ?: $r->batch->code }} ·
                        {{ $r->batch->ageInWeeks() }} weeks old ·
                        {{ number_format($r->batch->current_quantity) }} birds left
                    </span>
                </div>
            </div>

            @if ($r->quantity_culled > 0)
                <p class="assess-split">
                    {{ $r->quantity_died }} found dead · {{ $r->quantity_culled }} culled
                </p>
            @endif

            @if ($r->worker_note)
                <p class="assess-note">
                    <span>What the worker saw</span>
                    “{{ $r->worker_note }}”
                </p>
            @endif

            <p class="assess-meta">
                {{ $r->recorder->displayName() }} ·
                {{ $r->occurred_on->format('j M') }}
                @if ($waiting >= 1)
                    <em class="assess-waiting">waiting {{ $waiting }} {{ Str::plural('day', $waiting) }}</em>
                @endif
            </p>
        </a>
    @endforeach
@endif

@if ($recent->isNotEmpty())
    <h3 class="section-heading">Recently assessed</h3>

    <div class="card">
        @foreach ($recent as $r)
            <div class="done-row">
                <span>
                    <strong>{{ $r->totalLost() }}</strong>
                    {{ $r->batch->code }}
                </span>
                <span class="done-cause">
                    {{ $r->cause?->name ?? '—' }}
                    @if ($r->cause?->is_contagious)
                        <em class="tag-spread">spreads</em>
                    @endif
                </span>
                <span class="done-who">
                    {{ $r->assessor?->firstName() }} · {{ $r->assessed_at?->format('j M') }}
                </span>
            </div>
        @endforeach
    </div>
@endif

@endsection
