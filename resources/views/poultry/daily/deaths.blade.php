@extends('layouts.app')

@section('title', 'Deaths')

@section('content')

{{--
    Recording deaths.

    The worker is asked ONE thing: how many. They are never asked why.

    That is deliberate. A worker who finds four dead birds knows there are
    four. They do not know whether it was heat, coccidiosis or a snake, and
    a dropdown of diseases would produce a guess that afterwards looks
    exactly like a diagnosis. The cause is filled in by a manager, on a
    separate screen, and stored in separate fields.

    What the worker CAN usefully add is what they saw, in their own words.
    "They were breathing badly" is worth more to a manager than a wrong
    pick from a list.
--}}

{{-- Just a Back button: the screen name and batch line were removed at the user's request. --}}
<div class="entry-head entry-head--back"><a class="btn btn--quiet btn--inline entry-back" href="{{ request('from') === 'monitor' ? route('monitor.health', 'deaths') : route('daily.index') }}">&larr; Back</a></div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('daily.deaths.store', $batch) }}">@if(request('from') === 'monitor' || old('from') === 'monitor')<input type="hidden" name="from" value="monitor">@endif
    @csrf

    @include('poultry.daily._house_select', ['houseChoices' => $houseChoices, 'selectedHouse' => $selectedHouse, 'entryRoute' => 'daily.deaths'])

    <div class="ask">
        <p class="ask-q">How many birds died today?</p>
        <p class="ask-hint">If none died, leave it at 0 and save.</p>

        <div class="big-counter">
            <button type="button" class="big-step" data-target="died" data-step="-1" aria-label="One less">−</button>
            <input type="number" id="died" name="quantity_died" class="big-val"
                   value="{{ old('quantity_died', 0) }}" min="0" max="100000" inputmode="numeric">
            <button type="button" class="big-step" data-target="died" data-step="1" aria-label="One more">+</button>
        </div>
    </div>

    <details class="fold">
        <summary>Did you kill any sick or weak birds?</summary>

        <p class="ask-hint u-mt-12">
            Only birds you removed on purpose. Birds found dead go above.
        </p>

        <div class="big-counter big-counter--small">
            <button type="button" class="big-step" data-target="culled" data-step="-1" aria-label="One less">−</button>
            <input type="number" id="culled" name="quantity_culled" class="big-val"
                   value="{{ old('quantity_culled', 0) }}" min="0" max="100000" inputmode="numeric">
            <button type="button" class="big-step" data-target="culled" data-step="1" aria-label="One more">+</button>
        </div>
    </details>

    <div class="ask">
        <p class="ask-q">What did you see?</p>
        <p class="ask-hint">
            In your own words. Where you found them, how they looked, anything
            unusual. This helps the manager work out why. You can leave it empty.
        </p>
        <textarea name="worker_note" rows="3"
                  placeholder="e.g. found in the corner in the morning, they were breathing badly">{{ old('worker_note') }}</textarea>
    </div>

    <div class="reassure">
        You only need to say how many. A manager will decide the reason.
    </div>

    <button type="submit" class="btn-big">Save</button>
</form>

<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.big-step');
        if (!btn) return;

        var input = document.getElementById(btn.dataset.target);
        if (!input) return;

        var step = parseInt(btn.dataset.step, 10);
        var min  = parseInt(input.min, 10) || 0;
        var max  = parseInt(input.max, 10) || 99999;
        var now  = parseInt(input.value, 10) || 0;

        input.value = Math.min(max, Math.max(min, now + step));
    });
})();
</script>

@endsection
