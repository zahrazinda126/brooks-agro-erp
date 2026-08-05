@extends('layouts.app')

@section('title', 'Record health')

@section('content')

{{--
    Recording a vaccination, treatment or check.

    The medicine list is a dropdown rather than the long list of cards it
    used to be. Nineteen items is a lot of scrolling, and this screen is
    used by managers on a laptop — it does not need the big tap targets the
    worker screens are built around.

    What a dropdown loses is the information that was sitting on each card:
    what it treats, how it is given, and whether it holds the eggs. So that
    is not dropped, only moved — it appears underneath the moment something
    is chosen, which is when it is actually needed.
--}}

<div class="entry-head">
    <a class="back" href="{{ route('health.index') }}" aria-label="Go back">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 5 8 12l7 7" fill="none" stroke="currentColor"
                  stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
    <div>
        <h2>Record something</h2>
        <p>A vaccination, a treatment, a deworming or a health check.</p>
    </div>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('health.store') }}" id="healthForm">
    @csrf

    <div class="card">
        <div class="form-grid">
            <div class="field @error('animal_group_id') field--error @enderror">
                <label for="animal_group_id">Which flock <span class="req">required</span></label>
                <select id="animal_group_id" name="animal_group_id" required>
                    <option value="">Choose a flock</option>
                    @foreach ($batches as $b)
                        <option value="{{ $b->id }}"
                                @selected((int) old('animal_group_id', $preselect) === $b->id)>
                            {{ $b->code }} — {{ $b->name ?: $b->house }} ({{ $b->ageInWeeks() }} weeks)
                        </option>
                    @endforeach
                </select>
                @error('animal_group_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="event_type">What happened <span class="req">required</span></label>
                <select id="event_type" name="event_type" required data-type>
                    @foreach ([
                        'vaccination' => 'Vaccination',
                        'treatment'   => 'Treatment for illness',
                        'deworming'   => 'Deworming',
                        'inspection'  => 'Health check',
                        'vet_visit'   => 'Vet visit',
                    ] as $v => $label)
                        <option value="{{ $v }}" @selected(old('event_type', 'vaccination') === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field @error('occurred_on') field--error @enderror">
                <label for="occurred_on">Date <span class="req">required</span></label>
                <input id="occurred_on" name="occurred_on" type="date"
                       value="{{ old('occurred_on', now()->format('Y-m-d')) }}"
                       max="{{ now()->format('Y-m-d') }}" required>
                @error('occurred_on')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="birds_treated">How many birds</label>
                <input id="birds_treated" name="birds_treated" type="number"
                       inputmode="numeric" min="0"
                       value="{{ old('birds_treated') }}"
                       placeholder="Leave blank for the whole flock">
            </div>
        </div>
    </div>

    {{-- What was given. Hidden for a plain inspection or vet visit. --}}
    <div class="card" data-med-block>
        <p class="card-label">What was given <span class="req">required</span></p>

        @error('medication_id')<p class="field-error" style="margin-bottom:10px;">{{ $message }}</p>@enderror

        <div class="field">
            <label for="medication_id">Medicine or vaccine</label>
            <select id="medication_id" name="medication_id" data-med>
                <option value="">Choose one</option>

                @foreach ($medications as $type => $meds)
                    <optgroup label="{{ $meds->first()->typeLabel() }}">
                        @foreach ($meds as $med)
                            <option value="{{ $med->id }}"
                                    @selected((int) old('medication_id', $preselectMed) === $med->id)
                                    data-treats="{{ $med->treats }}"
                                    data-route="{{ $med->routeLabel() }}"
                                    data-dose="{{ $med->dose }}"
                                    data-note="{{ $med->note }}"
                                    data-withdrawal="{{ $med->withdrawal_days_eggs }}"
                                    data-banned="{{ $med->banned_in_lay ? '1' : '0' }}"
                                    data-name="{{ $med->name }}">
                               {{ $med->name }}{{ $med->withdrawal_days_eggs > 0 ? ' — holds eggs ' . $med->withdrawal_days_eggs . ' days' : '' }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
        </div>

        {{-- Everything the cards used to carry, shown once one is picked. --}}
        <div class="med-detail" data-med-detail hidden>
            <dl class="facts">
                <dt>Treats</dt><dd data-d-treats>—</dd>
                <dt>Given</dt><dd data-d-route>—</dd>
                <dt>Usual dose</dt><dd data-d-dose>—</dd>
            </dl>
            <p class="med-note" data-d-note hidden></p>
        </div>
    </div>

    {{-- Appears as soon as something that holds the eggs is chosen. --}}
    <div class="notice notice--alarm" id="withdrawWarn" hidden>
        <strong id="withdrawName">This</strong> holds the eggs.
        Nothing from this flock may be sold until
        <strong id="withdrawDate">the withdrawal ends</strong>.
    </div>

    <div class="notice notice--alarm" id="bannedWarn" hidden>
        <strong id="bannedName">This</strong> should not be given to birds in
        lay in many markets. Check with your vet before using it on layers.
    </div>

    <div class="card">
        <div class="form-grid">
            <div class="field @error('days_of_course') field--error @enderror">
                <label for="days_of_course">How many days is the course? <span class="req">required</span></label>
                <input id="days_of_course" name="days_of_course" type="number"
                       inputmode="numeric" min="1" max="60"
                       value="{{ old('days_of_course', 1) }}" required data-course>
                @error('days_of_course')<p class="field-error">{{ $message }}</p>@enderror
                <p class="field-hint">
                    1 for a single dose. Withdrawal counts from the LAST day, so a
                    five-day course holds the eggs five days longer.
                </p>
            </div>

            <div class="field">
                <label for="dose_given">Dose given</label>
                <input id="dose_given" name="dose_given" type="text"
                       value="{{ old('dose_given') }}"
                       placeholder="e.g. 1 sachet in 20 litres">
            </div>

            <div class="field">
                <label for="reason">Why</label>
                <input id="reason" name="reason" type="text"
                       value="{{ old('reason') }}"
                       placeholder="e.g. coughing seen in the morning">
            </div>

            <div class="field">
                <label for="cost">Cost (UGX)</label>
                <input id="cost" name="cost" type="number"
                       inputmode="numeric" min="0" step="1" value="{{ old('cost') }}">
                <p class="field-hint">Charged against this flock.</p>
            </div>

            <div class="field field--wide">
                <label for="findings">What was seen</label>
                <textarea id="findings" name="findings" rows="3"
                          placeholder="Signs, how the birds looked, what the vet said">{{ old('findings') }}</textarea>
            </div>

            <div class="field field--wide">
                <label for="administered_name">Given by, if not you</label>
                <input id="administered_name" name="administered_name" type="text"
                       value="{{ old('administered_name') }}"
                       placeholder="An outside vet, for instance">
            </div>
        </div>

        <label class="check">
            <input type="checkbox" name="vet_involved" value="1" @checked(old('vet_involved'))>
            <span><strong>A vet was involved</strong></span>
        </label>
    </div>

    <button type="submit" class="btn-big">Save</button>
</form>

<script>
/*
 | Everything below is feedback shown BEFORE saving, not after.
 |
 | The withdrawal date in particular: a manager choosing a drug needs to
 | know the eggs are held before they commit to it, not once it is done.
 */
(function () {
    var form  = document.getElementById('healthForm');
    if (!form) return;

    var block   = form.querySelector('[data-med-block]');
    var detail  = form.querySelector('[data-med-detail]');
    var warn    = document.getElementById('withdrawWarn');
    var banned  = document.getElementById('bannedWarn');

    function set(sel, value, fallback) {
        var el = form.querySelector(sel);
        if (el) el.textContent = value || fallback || '—';
    }

    function refresh() {
        var type = form.querySelector('[data-type]').value;

        // An inspection or a vet visit need not name a medicine.
        var needsMed = (type !== 'inspection' && type !== 'vet_visit');
        block.hidden = !needsMed;

        var select = form.querySelector('[data-med]');
        var opt    = select.options[select.selectedIndex];

        if (!needsMed || !opt || !opt.value) {
            detail.hidden = true;
            warn.hidden   = true;
            banned.hidden = true;
            return;
        }

        // What the cards used to show, now shown on demand.
        detail.hidden = false;
        set('[data-d-treats]', opt.dataset.treats, 'Not stated');
        set('[data-d-route]',  opt.dataset.route,  'Not stated');
        set('[data-d-dose]',   opt.dataset.dose,   'See the pack');

        var noteEl = form.querySelector('[data-d-note]');
        if (noteEl) {
            noteEl.textContent = opt.dataset.note || '';
            noteEl.hidden = !opt.dataset.note;
        }

        banned.hidden = opt.dataset.banned !== '1';
        if (!banned.hidden) {
            document.getElementById('bannedName').textContent = opt.dataset.name;
        }

        var days = parseInt(opt.dataset.withdrawal, 10) || 0;

        if (days < 1) {
            warn.hidden = true;
            return;
        }

        var course = parseInt(form.querySelector('[data-course]').value, 10) || 1;
        var start  = new Date(form.querySelector('[name=occurred_on]').value);

        if (isNaN(start.getTime())) { warn.hidden = true; return; }

        // Counted from the LAST day of the course, not the first.
        start.setDate(start.getDate() + (course - 1) + days);

        document.getElementById('withdrawName').textContent = opt.dataset.name;
        document.getElementById('withdrawDate').textContent =
            start.toLocaleDateString(undefined, { weekday: 'long', day: 'numeric', month: 'long' });

        warn.hidden = false;
    }

    form.addEventListener('change', refresh);
    form.addEventListener('input', refresh);
    refresh();
})();
</script>

@endsection
