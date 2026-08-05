@extends('layouts.app')

@section('title', 'Assess')

@section('content')

{{--
    The manager's assessment.

    Everything the worker recorded is shown at the top, plainly separated
    from what the manager is about to add. The distinction is the point of
    the whole design: an observation and a judgement are different kinds of
    fact and should never blur into each other.

    Recent deaths in the same flock are shown alongside, because a single
    bird is noise and four in three days is a pattern — and the pattern is
    usually what determines the cause.
--}}

<div class="entry-head">
    <a class="back" href="{{ route('mortality.assess.index') }}" aria-label="Go back">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 5 8 12l7 7" fill="none" stroke="currentColor"
                  stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
    <div>
        <h2>Assess deaths</h2>
        <p>{{ $record->batch->name ?: $record->batch->code }} ·
           {{ $record->occurred_on->format('j F Y') }}</p>
    </div>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

{{-- Stage 1: what was observed. Read-only. --}}
<div class="observed">
    <p class="observed-label">What the worker recorded</p>

    <div class="observed-figures">
        <div>
            <span class="figure figure--alarm">{{ $record->quantity_died }}</span>
            <span class="figure-unit">Found dead</span>
        </div>
        @if ($record->quantity_culled > 0)
            <div>
                <span class="figure">{{ $record->quantity_culled }}</span>
                <span class="figure-unit">Culled</span>
            </div>
        @endif
        <div>
            <span class="figure">{{ $record->batch->ageInWeeks() }}</span>
            <span class="figure-unit">Weeks old</span>
        </div>
        <div>
            <span class="figure">{{ number_format($record->batch->current_quantity) }}</span>
            <span class="figure-unit">Birds left</span>
        </div>
    </div>

    @if ($record->worker_note)
        <p class="observed-note">
            <span>{{ $record->recorder->displayName() }} wrote:</span>
            “{{ $record->worker_note }}”
        </p>
    @endif

    <p class="observed-meta">
        Recorded by {{ $record->recorder->displayName() }},
        {{ $record->recorded_at->format('j M \a\t H:i') }}
    </p>
</div>

{{-- Context: is this one event or a pattern? --}}
@if ($context->isNotEmpty())
    <div class="card">
        <p class="card-label">Same flock, last 14 days</p>
        <p class="muted" style="margin:0 0 12px;">
            {{ $recent14 }} birds lost in total, including this record.
        </p>

        @foreach ($context as $c)
            <div class="ctx-row">
                <span><strong>{{ $c->totalLost() }}</strong> on {{ $c->occurred_on->format('j M') }}</span>
                <span class="muted">{{ $c->cause?->name ?? 'not yet assessed' }}</span>
            </div>
        @endforeach
    </div>
@endif

{{-- Stage 2: the judgement. --}}
<form method="POST" action="{{ route('mortality.assess.update', $record) }}">
    @csrf
    @method('PUT')

    <div class="ask">
        <p class="ask-q">What caused this?</p>
        <p class="ask-hint">
            If you are not sure, choose <strong>Cause not established</strong>.
            A guess recorded today becomes a fact in next year's report.
        </p>

        @foreach ($causes as $category => $group)
            <p class="cause-group">{{ $group->first()->categoryLabel() }}</p>

            <div class="cause-list">
                @foreach ($group as $c)
                    <label class="cause">
                        <input type="radio" name="mortality_cause_id" value="{{ $c->id }}"
                               @checked((int) old('mortality_cause_id', $record->mortality_cause_id) === $c->id)
                               data-contagious="{{ $c->is_contagious ? '1' : '0' }}"
                               data-vet="{{ $c->needs_vet ? '1' : '0' }}"
                               data-name="{{ $c->name }}">
                        <span>
                            <strong>{{ $c->name }}</strong>
                            @if ($c->description)
                                <em>{{ $c->description }}</em>
                            @endif
                            @if ($c->is_contagious)
                                <span class="tag-spread">spreads through the flock</span>
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- Appears only when the chosen cause spreads. --}}
    <div class="notice notice--alarm" id="spreadWarn" hidden>
        <strong id="spreadName">This</strong> spreads through a flock.
        Say what was done about it, and check the rest of the birds today.
    </div>

    <div class="ask">
        <p class="ask-q">What did you do about it?</p>
        <p class="ask-hint">Treatment given, birds separated, house cleaned, water changed.</p>
        <textarea name="action_taken" rows="3"
                  placeholder="e.g. separated the sick birds, added vitamins to the water">{{ old('action_taken', $record->action_taken) }}</textarea>
    </div>

    <div class="ask">
        <p class="ask-q">Anything else worth recording?</p>
        <p class="ask-hint">Optional.</p>
        <textarea name="assessment_note" rows="3"
                  placeholder="e.g. litter was wet on the east side">{{ old('assessment_note', $record->assessment_note) }}</textarea>
    </div>

    <label class="check" style="margin-bottom:18px;">
        <input type="checkbox" name="vet_called" value="1"
               @checked(old('vet_called', $record->vet_called))>
        <span><strong>A vet was called</strong></span>
    </label>

    <button type="submit" class="btn-big">Save assessment</button>
</form>

<script>
/* Warn as soon as a contagious cause is picked, not after saving. */
(function () {
    var warn = document.getElementById('spreadWarn');
    var name = document.getElementById('spreadName');
    if (!warn) return;

    document.addEventListener('change', function (e) {
        if (e.target.name !== 'mortality_cause_id') return;

        var spreads = e.target.dataset.contagious === '1';
        warn.hidden = !spreads;

        if (spreads) {
            name.textContent = e.target.dataset.name;
            warn.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    var checked = document.querySelector('[name=mortality_cause_id]:checked');
    if (checked && checked.dataset.contagious === '1') {
        warn.hidden = false;
        name.textContent = checked.dataset.name;
    }
})();
</script>

@endsection
