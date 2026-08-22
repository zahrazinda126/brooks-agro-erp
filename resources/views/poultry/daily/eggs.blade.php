@extends('layouts.app')

@section('title', 'Eggs')

@section('content')

{{--
    Egg entry.

    Counting is done with big + and - buttons, not by typing. A worker in a
    house has one hand free and possibly wet fingers; a keypad is slower
    and easier to mis-hit than a 56px button.

    TWO TIMES ARE INVOLVED and they are not the same:

      the time asked for below   when the eggs came out of the house
      the time recorded silently when this form is submitted

    A worker collects at half past six and types it in at nine when they
    next have signal. Both facts matter, so both are kept — the first is
    the worker's statement, the second is the system's.
--}}

<div class="entry-head">
    <a class="back" href="{{ route('daily.index') }}" aria-label="Go back">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 5 8 12l7 7" fill="none" stroke="currentColor"
                  stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
    <div>
        <h2>Eggs</h2>
        <p>{{ $batch->name ?: $batch->code }} · {{ number_format($batch->current_quantity) }} birds</p>
    </div>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('daily.eggs.store', $batch) }}" id="eggForm">
    @csrf

    @include('poultry.daily._house_select', ['houseChoices' => $houseChoices, 'selectedHouse' => $selectedHouse, 'entryRoute' => 'daily.eggs'])

    {{-- Which round --}}
    <div class="ask">
        <p class="ask-q">Collection period</p>
        <p class="ask-hint">Detected automatically from the current time.</p>
        <div class="choice-row">
            @foreach (['morning' => 'Morning', 'afternoon' => 'Afternoon', 'evening' => 'Evening'] as $v => $label)
                <label class="choice">
                    <input type="radio" value="{{ $v }}" @checked($session === $v) disabled>
                    <span>{{ $label }}</span>
                </label>
            @endforeach
        </div>
    </div>

    {{-- Running total, always visible --}}
    <div class="total-bar" id="totalBar">
        <span class="total-num" id="totalEggs">0</span>
        <span class="total-lbl">eggs so far</span>
    </div>

    <p class="ask-q" style="margin-top:22px;">How many did you collect?</p>
    <p class="ask-hint">Count full trays first, then the loose eggs left over.</p>

    @foreach ($likely as $grade)
        <div class="counter-card">
            <div class="counter-head">
                <strong>{{ $grade->name }}</strong>
                @if ($grade->min_weight_g)
                    <span>{{ $grade->weightRange() }}</span>
                @endif
            </div>

            <div class="counter-row">
                <span class="counter-what">Trays</span>
                <div class="stepper">
                    <button type="button" class="step" data-step="-1" aria-label="One less tray">−</button>
                    <input type="number" name="trays[{{ $grade->id }}]" class="step-val"
                           value="{{ old('trays.'.$grade->id, 0) }}"
                           min="0" max="2000" inputmode="numeric" data-eggs="{{ $grade->eggs_per_tray }}">
                    <button type="button" class="step" data-step="1" aria-label="One more tray">+</button>
                </div>
            </div>

            <div class="counter-row counter-row--minor">
                <span class="counter-what">Loose eggs</span>
                <div class="stepper">
                    <button type="button" class="step" data-step="-1" aria-label="One less egg">−</button>
                    <input type="number" name="loose[{{ $grade->id }}]" class="step-val"
                           value="{{ old('loose.'.$grade->id, 0) }}"
                           min="0" max="29" inputmode="numeric" data-eggs="1">
                    <button type="button" class="step" data-step="1" aria-label="One more egg">+</button>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Defects, folded away. Usually zero, and giving them the same weight
         as good eggs makes the screen twice as long for nothing. --}}
    <details class="fold">
        <summary>Any broken, cracked or dirty eggs?</summary>

        @foreach ($defects as $grade)
            <div class="counter-card counter-card--defect">
                <div class="counter-head"><strong>{{ $grade->name }}</strong></div>

                <div class="counter-row">
                    <span class="counter-what">Trays</span>
                    <div class="stepper">
                        <button type="button" class="step" data-step="-1" aria-label="One less">−</button>
                        <input type="number" name="trays[{{ $grade->id }}]" class="step-val"
                               value="{{ old('trays.'.$grade->id, 0) }}"
                               min="0" max="2000" inputmode="numeric" data-eggs="{{ $grade->eggs_per_tray }}">
                        <button type="button" class="step" data-step="1" aria-label="One more">+</button>
                    </div>
                </div>

                <div class="counter-row counter-row--minor">
                    <span class="counter-what">Loose eggs</span>
                    <div class="stepper">
                        <button type="button" class="step" data-step="-1" aria-label="One less">−</button>
                        <input type="number" name="loose[{{ $grade->id }}]" class="step-val"
                               value="{{ old('loose.'.$grade->id, 0) }}"
                               min="0" max="29" inputmode="numeric" data-eggs="1">
                        <button type="button" class="step" data-step="1" aria-label="One more">+</button>
                    </div>
                </div>
            </div>
        @endforeach
    </details>

    <div class="ask">
        <p class="ask-q">House daily measurements</p>
        <p class="ask-hint">House: {{ $batch->house ?: $batch->code }}. Leave a measurement empty when it was not taken.</p>
        <div class="form-grid">
            <div class="field"><label>Egg mass (g)</label><input type="number" name="egg_mass_g" value="{{ old('egg_mass_g') }}" min="0" step="0.01" inputmode="decimal"></div>
            <div class="field"><label>Bird weight (kg)</label><input type="number" name="bird_weight_kg" value="{{ old('bird_weight_kg') }}" min="0" step="0.01" inputmode="decimal"></div>
        </div>
    </div>

    <div class="ask">
        <p class="ask-q">Anything to tell the supervisor?</p>
        <p class="ask-hint">Only if something was unusual. You can leave this empty.</p>
        <textarea name="worker_note" rows="2"
                  placeholder="e.g. many eggs on the floor today">{{ old('worker_note') }}</textarea>
    </div>

    {{-- Said plainly rather than hidden. Nobody should be surprised later
         to find their name on a record. --}}
    <p class="signed-note">
        This will be saved as <strong>{{ $user->displayName() }}</strong>,
        with the time you press save.
    </p>

    <button type="submit" class="btn-big">Save</button>
</form>

<script>
/* Steppers and a live total. Plain JS — nothing to download, works on any
   phone that can open the page at all. */
(function () {
    var form = document.getElementById('eggForm');
    var out  = document.getElementById('totalEggs');
    if (!form || !out) return;

    function recount() {
        var total = 0;
        form.querySelectorAll('.step-val').forEach(function (input) {
            var n = parseInt(input.value, 10) || 0;
            total += n * (parseInt(input.dataset.eggs, 10) || 1);
        });
        out.textContent = total.toLocaleString();
    }

    form.addEventListener('click', function (e) {
        var btn = e.target.closest('.step');
        if (!btn) return;

        var input = btn.parentNode.querySelector('.step-val');
        var step  = parseInt(btn.dataset.step, 10);
        var min   = parseInt(input.min, 10) || 0;
        var max   = parseInt(input.max, 10) || 9999;
        var now   = parseInt(input.value, 10) || 0;

        input.value = Math.min(max, Math.max(min, now + step));
        recount();
    });

    form.addEventListener('input', function (e) {
        if (e.target.classList.contains('step-val')) recount();
    });

    recount();
})();
</script>

@endsection
