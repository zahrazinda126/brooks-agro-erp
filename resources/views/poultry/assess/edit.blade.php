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
    <p class="observed-meta">
        Batch {{ $record->batch->code }} &middot;
        House: {{ $record->house?->house_number ?? 'Not recorded' }}
    </p>

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
            <span class="figure-unit">Birds currently in batch {{ $record->batch->code }}</span>
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
        <p class="muted u-m0-b12">
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
        <label class="ask-q" for="cause_name">What caused this?</label>
        <p class="ask-hint">
            If you are not sure, choose <strong>Cause not established</strong>.
            A guess recorded today becomes a fact in next year's report.
        </p>

        <div class="field cause-picker">
            <div class="cause-picker__control">
            <input id="cause_name" name="cause_name" type="text" role="combobox" aria-expanded="false" aria-controls="mortality-causes" aria-autocomplete="list"
                   value="{{ old('cause_name', $record->cause?->name) }}" maxlength="100"
                   placeholder="Choose or type a cause" required autocomplete="off"
                   aria-describedby="cause-description">
            <button type="button" class="cause-picker__toggle" aria-label="Show causes" aria-controls="mortality-causes" aria-expanded="false">&#9662;</button>
            </div>
            <div id="mortality-causes" class="cause-picker__list" role="listbox" aria-label="Causes" hidden>
                @foreach ($causes as $group)
                    <div role="group" aria-label="{{ $group->first()->categoryLabel() }}">
                    <div class="cause-picker__heading">{{ $group->first()->categoryLabel() }}</div>
                    @foreach ($group as $c)
                        <div role="option" aria-selected="false" id="cause-option-{{ $c->id }}" class="cause-picker__option" data-value="{{ $c->name }}" data-contagious="{{ $c->is_contagious ? '1' : '0' }}"
                                data-description="{{ $c->description }}">{{ $c->name }}</div>
                    @endforeach
                    </div>
                @endforeach
                <p class="field-hint cause-picker__empty" hidden>No matching cause. Your typed cause will be saved.</p>
            </div>
            <p class="field-hint" id="cause-description" aria-live="polite" hidden></p>
            @error('cause_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>
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
        <input type="checkbox" id="vet_called" name="vet_called" value="1"
               @checked(old('vet_called', $record->vet_called))>
        <span><strong>A vet was called</strong></span>
    </label>

    <div class="field" id="vet-name-field" @if(!old('vet_called', $record->vet_called)) hidden @endif>
        <label for="vet_name">Vet's name <span class="req">required</span></label>
        <input id="vet_name" name="vet_name" type="text" maxlength="160"
               value="{{ old('vet_name', $record->vet_name) }}"
               @required(old('vet_called', $record->vet_called))>
        @error('vet_name')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <button type="submit" class="btn btn--inline btn--small">Save assessment</button>
</form>

@pushOnce('styles')<style>
.cause-picker{position:relative}.cause-picker__control{position:relative}.cause-picker__control input{width:100%;padding-right:42px}.cause-picker__toggle{position:absolute;right:2px;top:2px;bottom:2px;width:38px;border:0;background:transparent;color:inherit;cursor:pointer}.cause-picker__list{position:absolute;top:52px;left:0;right:0;z-index:30;max-height:320px;overflow-y:auto;background:#fff;color:#171717;border:1px solid #c9beb4;border-radius:8px;box-shadow:0 8px 24px #0002;padding:8px 0}.cause-picker__heading{padding:6px 16px 3px;font-weight:700;color:#921f2b}.cause-picker__option{padding:7px 24px;cursor:pointer}.cause-picker__option:hover,.cause-picker__option[aria-selected="true"]{background:var(--maroon-700);color:#fff}.cause-picker__empty{padding:8px 16px}
</style>@endPushOnce
<script>
(function () {
    var vetCalled = document.getElementById('vet_called');
    var vetName = document.getElementById('vet_name');
    function updateVet() {
        document.getElementById('vet-name-field').hidden = !vetCalled.checked;
        vetName.required = vetCalled.checked;
        vetName.disabled = !vetCalled.checked;
    }
    vetCalled.addEventListener('change', updateVet);
    updateVet();
    var select = document.getElementById('cause_name');
    var description = document.getElementById('cause-description');
    var warn = document.getElementById('spreadWarn');
    var name = document.getElementById('spreadName');
    var action = select.form.querySelector('[name="action_taken"]');
    var options = Array.from(document.querySelectorAll('#mortality-causes [role=option]'));

    function updateCause() {
        var value = select.value.trim().toLowerCase();
        var selected = options.find(option => option.dataset.value.toLowerCase() === value);
        var spreads = selected && selected.dataset.contagious === '1';
        description.textContent = selected ? selected.dataset.description || '' : '';
        description.hidden = !description.textContent;
        warn.hidden = !spreads;
        action.required = Boolean(spreads);
        name.textContent = selected ? selected.dataset.value : 'This';
    }

    var picker = select.closest('.cause-picker');
    var list = document.getElementById('mortality-causes');
    var toggle = picker.querySelector('.cause-picker__toggle');
    var active = -1;
    function visibleOptions() { return options.filter(option => !option.hidden); }
    function highlight(index) {
        var visible = visibleOptions();
        active = index;
        options.forEach(option => option.setAttribute('aria-selected', 'false'));
        select.removeAttribute('aria-activedescendant');
        if (visible[index]) {
            visible[index].setAttribute('aria-selected', 'true');
            select.setAttribute('aria-activedescendant', visible[index].id);
            visible[index].scrollIntoView({block:'nearest'});
        }
    }
    function close() {
        list.hidden = true;
        select.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-expanded', 'false');
        highlight(-1);
    }
    function open(filter) {
        var query = filter ? select.value.trim().toLowerCase() : '';
        options.forEach(option => { option.hidden = !option.dataset.value.toLowerCase().includes(query); });
        list.querySelectorAll('[role=group]').forEach(group => {
            group.hidden = !Array.from(group.querySelectorAll('[role=option]')).some(option => !option.hidden);
        });
        list.querySelector('.cause-picker__empty').hidden = visibleOptions().length > 0;
        list.hidden = false;
        select.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-expanded', 'true');
        highlight(-1);
    }
    function choose(option) {
        select.value = option.dataset.value;
        updateCause();
        select.focus();
        close();
    }
    options.forEach(option => option.addEventListener('click', () => choose(option)));
    select.addEventListener('click', () => open(false));
    select.addEventListener('input', () => { updateCause(); open(true); });
    toggle.addEventListener('click', () => { if (list.hidden) { select.focus(); open(false); } else close(); });
    picker.addEventListener('focusout', event => { if (!picker.contains(event.relatedTarget)) close(); });
    document.addEventListener('pointerdown', event => { if (!picker.contains(event.target)) close(); });
    list.addEventListener('mousedown', event => event.preventDefault());
    select.addEventListener('keydown', event => {
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            if (list.hidden) open(false);
            var count = visibleOptions().length;
            if (count) highlight(event.key === 'ArrowDown' ? (active + 1) % count : (active <= 0 ? count - 1 : active - 1));
        }
        if (event.key === 'Enter' && !list.hidden && active >= 0) {
            event.preventDefault(); choose(visibleOptions()[active]);
        }
        if (event.key === 'Tab') close();
    });
    updateCause();
})();
</script>

@endsection
