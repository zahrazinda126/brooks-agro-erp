@extends('layouts.app')

@section('title', $batch->code)

@section('content')

@php
    $weeks    = $batch->ageInWeeks();
    $toLay    = $batch->weeksToLay();
    $expected = $batch->purpose === 'broiler' ? collect() : $batch->expectedGrades();
@endphp

<div class="page-head page-head--split">
    <div>
        <h2>{{ $batch->code }}</h2>
        <p>
            {{ $batch->name ?: $batch->breed?->name ?? 'Batch' }}
            @if ($batch->house) · {{ $batch->house }} @endif
            <span class="stage stage--{{ Str::slug($batch->stage()) }}">{{ $batch->stage() }}</span>
            @if (! $batch->isActive())
                <span class="pill pill--muted">Closed</span>
            @endif
        </p>
    </div>

    @if ($user->canDo('batches', 'edit') && $batch->isActive() && $batch->mayBeEditedBy($user))
        <a class="btn btn--quiet btn--inline" href="{{ route('batches.edit', $batch) }}">Edit (Director allowed)</a>
    @endif
</div>

@php
    $roleCodes = $user->roles->pluck('code')->push($user->role?->code);
@endphp
@if($batch->isActive() && $user->canDo('batches','edit') && !$batch->mayBeEditedBy($user))
<section class="card"><p class="card-label">Protected batch record</p><h3>Director permission is required</h3><p>Initial batch details are locked. House quantities continue to change through transfers, mortality and other recorded movements.</p><form method="POST" action="{{ route('batches.request-edit',$batch) }}">@csrf<div class="field"><label>Why must the initial batch record change?</label><textarea name="reason" required></textarea></div><button class="btn">Request editing permission</button></form></section>
@endif
@if($roleCodes->contains('director') && $batch->edit_requested_by && (!$batch->edit_authorized_until || $batch->edit_authorized_until->isPast()))
<section class="card"><p class="card-label">Editing request</p><h3>A manager wants to change this batch</h3><p>Requested {{ $batch->edit_requested_at?->diffForHumans() }}.</p><form method="POST" action="{{ route('batches.authorize-edit',$batch) }}">@csrf<div class="field"><label>Allow editing for</label><select name="hours"><option value="2">2 hours</option><option value="8">8 hours</option><option value="24" selected>24 hours</option><option value="72">3 days</option></select></div><button class="btn">Allow editing</button></form></section>
@endif

{{-- Anything wrong or upcoming, said plainly and before the numbers. --}}
@if ($batch->layingIsOverdue())
    <div class="notice notice--alarm">
        <strong>Laying is overdue.</strong>
        This flock is {{ $weeks }} weeks old and {{ $batch->breed?->name ?? 'the breed' }}
        normally starts at week {{ $batch->breed?->lay_start_week ?? 18 }}. No first egg has
        been recorded. Worth checking feed, lighting and body weight.
    </div>
@elseif ($batch->mortalityIsHigh())
    <div class="notice notice--alarm">
        <strong>Mortality is high for this age.</strong>
        {{ number_format($batch->total_mortality) }} of
        {{ number_format($batch->liveReceived()) }} birds available after arrival
        ({{ $batch->mortalityRate() }}%) at {{ $weeks }} weeks.
    </div>
@elseif ($batch->isActive() && $toLay !== null && $toLay > 0 && $toLay <= 3)
    <div class="notice">
        <strong>First eggs due soon.</strong>
        Expected in {{ $toLay }} {{ Str::plural('week', $toLay) }}@if ($batch->expected_lay_date), around {{ $batch->expected_lay_date->format('j F') }}@endif.
        The first eggs will be starter size — small is normal at this stage.
    </div>
@endif

<div class="grid">
    <div class="card">
        <p class="card-label">Birds alive</p>
        <span class="figure">{{ number_format($batch->current_quantity) }}</span>
        <span class="figure-unit">of {{ number_format($batch->liveReceived()) }} received alive</span>
    </div>

    <div class="card">
        <p class="card-label">Age</p>
        <span class="figure">{{ $weeks }}</span>
        <span class="figure-unit">Weeks · {{ number_format($batch->ageInDays()) }} days</span>
    </div>

    <div class="card">
        <p class="card-label">Mortality</p>
        <span class="figure {{ $batch->mortalityIsHigh() ? 'figure--alarm' : '' }}">{{ $batch->mortalityRate() }}%</span>
        <span class="figure-unit">{{ number_format($batch->total_mortality) }} birds</span>
    </div>

    @if(!auth()->user()->isProductionUser())
    <div class="card">
        <p class="card-label">Bird cost each</p>
        <span class="figure">{{ number_format($batch->costPerBirdAlive()) }}</span>
        <span class="figure-unit">UGX · birds only, spread over survivors</span>
    </div>
    @endif
</div>

<div class="detail-grid" style="margin-top:12px;">

    {{--
        The laying timeline. This is what the age tracking is for: it turns
        "the eggs are small" into either "normal, she's 20 weeks old" or
        "something is wrong, she's 40".
    --}}
    @if ($batch->purpose !== 'broiler')
        <div class="card">
            <p class="card-label">Laying</p>

            <dl class="facts">
                <dt>Breed starts</dt>
                <dd>Week {{ $batch->breed?->lay_start_week ?? '—' }}</dd>

                <dt>Expected</dt>
                <dd>{{ $batch->expected_lay_date?->format('j M Y') ?? 'Breed not stated' }}</dd>

                <dt>Actually started</dt>
                <dd>
                    @if ($batch->actual_lay_date)
                        {{ $batch->actual_lay_date->format('j M Y') }}
                        @php
                            $diff = $batch->expected_lay_date?->diffInDays($batch->actual_lay_date, false);
                        @endphp
                        @if ($diff !== null)
                            <span class="pill {{ $diff > 7 ? 'pill--muted' : '' }}">
                                {{ $diff > 0 ? "{$diff} days late" : ($diff < 0 ? abs($diff) . ' days early' : 'on time') }}
                            </span>
                        @endif
                    @else
                        <span class="muted">Not yet — recorded on the first egg collection</span>
                    @endif
                </dd>

                <dt>Peak due</dt>
                <dd>Week {{ $batch->breed?->peak_week ?? '—' }}
                    @if ($batch->breed?->peak_lay_percent)
                        <span class="muted">at about {{ $batch->breed->peak_lay_percent }}%</span>
                    @endif
                </dd>

                <dt>Cull around</dt>
                <dd>Week {{ $batch->breed?->lay_end_week ?? '—' }}</dd>
            </dl>
        </div>

        {{-- What size eggs this flock should be producing right now. --}}
        <div class="card">
            <p class="card-label">Egg size expected now</p>

            @if (! $batch->shouldBeLaying())
                <p class="muted" style="margin:0;">
                    Not laying yet. When it starts, expect <strong>starter</strong> eggs —
                    a hen's first eggs are small and grow with her over the following
                    weeks.
                </p>
            @elseif ($expected->isEmpty())
                <p class="muted" style="margin:0;">
                    Past the normal laying window. Size and shell quality usually fall
                    off at this age.
                </p>
            @else
                <p class="muted" style="margin:0 0 12px;">
                    At {{ $weeks }} weeks, most eggs should be:
                </p>

                <div class="grade-list">
                    @foreach ($expected as $g)
                        <div class="grade-row">
                            <strong>{{ $g->name }}</strong>
                            <span class="muted">{{ $g->weightRange() }}</span>
                        </div>
                    @endforeach
                </div>

                <p class="field-hint" style="margin-top:12px;">
                    Smaller sizes than this from a flock of {{ $weeks }} weeks usually
                    points at feed, water or disease rather than the birds.
                </p>
            @endif
        </div>
    @else
        <div class="card">
            <p class="card-label">Growing</p>
            <dl class="facts">
                <dt>Ready at</dt>
                <dd>Week {{ $batch->breed?->slaughter_week ?? '—' }}</dd>
                <dt>Target weight</dt>
                <dd>{{ $batch->breed?->mature_weight_g ? number_format($batch->breed->mature_weight_g) . ' g' : '—' }}</dd>
            </dl>
        </div>
    @endif

    <div class="card">
        <p class="card-label">Arrival</p>
        <dl class="facts">
            <dt>Received</dt><dd>{{ $batch->received_date->format('j M Y') }}</dd>
            <dt>Age then</dt>
            <dd>
                {{ $batch->age_days_on_arrival }} days
                @if ($batch->age_days_on_arrival === 0)
                    <span class="muted">(day old)</span>
                @endif
            </dd>
            <dt>Birds paid for</dt><dd>{{ number_format($batch->quantity_received) }}</dd>
            <dt>Free birds</dt><dd>{{ number_format($batch->quantity_free) }}</dd>
            <dt>Died in transit</dt><dd>{{ number_format($batch->transit_deaths) }}</dd>
            <dt>Received alive</dt><dd><strong>{{ number_format($batch->liveReceived()) }}</strong></dd>
            @if(!auth()->user()->isProductionUser())
            <dt>Cost each</dt><dd>{{ number_format((float) $batch->unit_cost) }} UGX</dd>
            <dt>Total</dt><dd><strong>{{ number_format((float) $batch->total_cost) }} UGX</strong></dd>
            @endif
            <dt>Supplier</dt><dd>{{ $batch->supplier_name ?: '—' }}</dd>
            <dt>Invoice</dt><dd class="mono">{{ $batch->invoice_no ?: '—' }}</dd>
        </dl>
    </div>

    <div class="card">
        <p class="card-label">Losses</p>
        <dl class="facts">
            <dt>Died</dt><dd>{{ number_format($batch->total_mortality) }}</dd>
            <dt>Culled</dt><dd>{{ number_format($batch->total_culled) }}</dd>
            <dt>Sold</dt><dd>{{ number_format($batch->total_sold) }}</dd>
            <dt>Remaining</dt><dd><strong>{{ number_format($batch->current_quantity) }}</strong></dd>
        </dl>

        <p class="field-hint" style="margin-top:12px;">
            These update as daily records are entered.
        </p>
    </div>
</div>

@if ($batch->notes)
    <div class="card">
        <p class="card-label">Notes</p>
        <p style="margin:0;white-space:pre-wrap;">{{ $batch->notes }}</p>
    </div>
@endif

@if ($user->canDo('batches', 'edit') && $batch->isActive())
    <details class="card" style="margin-top:12px;">
        <summary class="card-label" style="cursor:pointer;">Close this batch</summary>

        <p class="muted" style="margin:12px 0;">
            Closing stops new records against it. Nothing is deleted — the batch and
            its whole history stay, which is what makes its final cost and return
            answerable later.
        </p>

        <form method="POST" action="{{ route('batches.close', $batch) }}">
            @csrf
            <div class="field @error('reason') field--error @enderror">
                <label for="reason">Why is it being closed?</label>
                <input id="reason" name="reason" type="text" required
                       placeholder="e.g. Flock sold to Kalerwe market">
                @error('reason')<p class="field-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn btn--quiet btn--inline"
                    onclick="return confirm('Close batch {{ $batch->code }}?')">
                Close batch
            </button>
        </form>
    </details>
@endif

<p style="margin-top:18px;">
    <a class="btn btn--quiet btn--inline" href="{{ route('batches.index') }}">Back to batches</a>
</p>

@endsection
