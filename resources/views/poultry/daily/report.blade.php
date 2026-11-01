@extends('layouts.app')

@section('title', $meta['English'])

@section('content')
{{--
    Water, sick birds and problems: the worker says what and how many, in their own words.
    The supervisor checks it in Daily Collection Approvals; sick birds and problems also go
    straight to the Production Manager.
--}}
<div class="entry-head entry-head--back"><a class="btn btn--quiet btn--inline entry-back" href="{{ route('daily.index') }}">&larr; Back</a></div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route($meta['route'].'.store', $batch) }}">
    @csrf

    @include('poultry.daily._house_select', ['houseChoices' => $houseChoices, 'selectedHouse' => $selectedHouse, 'entryRoute' => $meta['route']])

    @if ($type === 'water')
        <div class="ask">
            <p class="ask-q">How many litres of water did you give today?</p>
            <p class="ask-hint">All the water you gave this house today.</p>
            <div class="big-counter">
                <button type="button" class="big-step" data-target="qty" data-step="-10" aria-label="Ten litres less">−</button>
                <input type="number" id="qty" name="quantity" class="big-val" value="{{ old('quantity', 0) }}" min="0" max="1000000" step="1" inputmode="numeric">
                <button type="button" class="big-step" data-target="qty" data-step="10" aria-label="Ten litres more">+</button>
            </div>
            <p class="ask-hint u-mt-12" style="text-align:center">litres · the buttons add or take away 10</p>
        </div>
        <div class="ask">
            <p class="ask-q">Anything to add?</p>
            <p class="ask-hint">You can leave this empty.</p>
            <textarea name="details" rows="2" placeholder="e.g. drinkers in the corner were empty in the morning">{{ old('details') }}</textarea>
        </div>
    @elseif ($type === 'sick_birds')
        <div class="ask">
            <p class="ask-q">How many birds look sick or weak?</p>
            <p class="ask-hint">Birds that are still alive. Birds that died go under Deaths.</p>
            <div class="big-counter">
                <button type="button" class="big-step" data-target="qty" data-step="-1" aria-label="One less">−</button>
                <input type="number" id="qty" name="quantity" class="big-val" value="{{ old('quantity', 1) }}" min="1" max="1000000" inputmode="numeric">
                <button type="button" class="big-step" data-target="qty" data-step="1" aria-label="One more">+</button>
            </div>
        </div>
        <div class="ask">
            <p class="ask-q">What did you see?</p>
            <p class="ask-hint">In your own words: how they look, how they breathe, their droppings, where they are.</p>
            <textarea name="details" rows="3" required placeholder="e.g. sitting alone, eyes closed, not eating, breathing loudly">{{ old('details') }}</textarea>
        </div>
        <div class="reassure">The manager is told straight away and will decide what to do.</div>
    @else
        <div class="ask">
            <p class="ask-q">What is the problem about?</p>
            <div class="choice-row choice-row--wrap">
                @foreach ($problems as $key => $label)
                    <label class="choice">
                        <input type="radio" name="category" value="{{ $key }}" @checked(old('category') === $key) required>
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
        <div class="ask">
            <p class="ask-q">What is wrong?</p>
            <p class="ask-hint">Say what you saw and where.</p>
            <textarea name="details" rows="3" required placeholder="e.g. two drinkers are leaking in the back of the house">{{ old('details') }}</textarea>
        </div>
        <div class="reassure">The manager is told straight away.</div>
    @endif

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
        var min = parseInt(input.min, 10) || 0, max = parseInt(input.max, 10) || 999999;
        input.value = Math.min(max, Math.max(min, (parseInt(input.value, 10) || 0) + step));
    });
})();
</script>
@endsection
