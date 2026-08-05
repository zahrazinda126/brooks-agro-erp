@extends('layouts.app')

@section('title', 'Feed')

@section('content')

{{--
    Feed used today.

    On bag weights: the manager sets each feed's usual sack size on the Feed
    Types screen, so the worker normally just counts bags and never touches
    the weight at all. That is the common path and it stays to one counter.

    Behind a "change" link sits everything else, because reality is untidy:
    concentrate comes in 25 kg, premix in 5 or 2, maize in 70 or 90, and a
    good deal of feed is scooped and weighed loose rather than bagged. A
    screen that only offers 50 and 70 gets worked around — someone enters
    "1 bag" for a half sack and the conversion figures quietly rot.
--}}

@php
    $weeks = $batch->ageInWeeks();
@endphp

<div class="entry-head">
    <a class="back" href="{{ route('daily.index') }}" aria-label="Go back">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M15 5 8 12l7 7" fill="none" stroke="currentColor"
                  stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </a>
    <div>
        <h2>Feed</h2>
        <p>{{ $batch->name ?: $batch->code }} · {{ number_format($batch->current_quantity) }} birds</p>
    </div>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

@if ($existing->isNotEmpty())
    <div class="notice">
        You already recorded feed for this flock today. Saving again will replace it.
    </div>
@endif

<form method="POST" action="{{ route('daily.feed.store', $batch) }}" id="feedForm">
    @csrf

    <div class="total-bar">
        <span class="total-num" id="totalKg">0</span>
        <span class="total-lbl">kg altogether</span>
    </div>

    <p class="ask-q" style="margin-top:22px;">Which feed did you use?</p>
    <p class="ask-hint">Tap each one you used. You can choose more than one.</p>

    @foreach ($groups as $category => $feeds)
        <p class="feed-group">{{ $feeds->first()->categoryLabel() }}</p>

        <div class="feed-list">
            @foreach ($feeds as $feed)
                @php
                    $row      = $existing->firstWhere('feed_type_id', $feed->id);
                    $chosen   = old("use.{$feed->id}", $row ? '1' : null);
                    $suits    = $feed->suitsWeek($weeks);
                    $bagKg    = (int) old("kg.{$feed->id}", $row->kg_per_bag ?? $feed->default_kg_per_bag);
                    $isLoose  = old("loose.{$feed->id}", ($row && $row->bags === 0) ? '1' : null);
                    $quick    = [2, 5, 10, 20, 25, 40, 50, 70, 90, 100];
                @endphp

                <div class="feed {{ $suits ? '' : 'feed--unlikely' }}" data-feed>

                    <label class="feed-pick">
                        <input type="checkbox" name="use[{{ $feed->id }}]" value="1"
                               @checked($chosen) data-toggle>
                        <span class="feed-name">
                            <strong>{{ $feed->name }}</strong>
                            @if ($feed->note)
                                <em>{{ $feed->note }}</em>
                            @endif
                        </span>
                    </label>

                    <div class="feed-amount" data-amount @if (! $chosen) hidden @endif>

                        {{-- Bags. The normal way. --}}
                        <div data-mode="bags" @if ($isLoose) hidden @endif>
                            <div class="feed-row">
                                <span class="feed-what">Bags</span>
                                <div class="stepper">
                                    <button type="button" class="step" data-step="-1" aria-label="One less bag">−</button>
                                    <input type="number" class="step-val" data-bags
                                           name="bags[{{ $feed->id }}]"
                                           value="{{ old("bags.{$feed->id}", $row->bags ?? 0) }}"
                                           min="0" max="999" inputmode="numeric">
                                    <button type="button" class="step" data-step="1" aria-label="One more bag">+</button>
                                </div>
                            </div>

                            <p class="bag-size">
                                <span data-bagsize>{{ $bagKg }}</span> kg in each bag
                                <button type="button" class="link-button" data-open-size>change</button>
                            </p>

                            {{-- Only opened when the sack is an unusual size. --}}
                            <div class="size-pick" data-size-pick hidden>
                                <p class="ask-hint" style="margin:0 0 8px;">How heavy is one bag?</p>

                                <div class="weight-pick">
                                    @foreach ($quick as $kg)
                                        <label class="weight">
                                            <input type="radio" name="kg[{{ $feed->id }}]" value="{{ $kg }}"
                                                   data-kg @checked($bagKg === $kg)>
                                            <span>{{ $kg }}</span>
                                        </label>
                                    @endforeach
                                </div>

                                <div class="feed-row feed-row--minor" style="margin-top:6px;">
                                    <span class="feed-what">Another size</span>
                                    <input type="number" class="kg-custom" data-kg-custom
                                           placeholder="kg" min="1" max="500" inputmode="numeric"
                                           value="{{ in_array($bagKg, $quick, true) ? '' : $bagKg }}">
                                </div>

                                {{-- Carries whatever was chosen, quick pick or typed. --}}
                                <input type="hidden" name="kg[{{ $feed->id }}]" data-kg-final value="{{ $bagKg }}">
                            </div>
                        </div>

                        {{-- Weighed loose, not bagged. --}}
                        <div data-mode="loose" @if (! $isLoose) hidden @endif>
                            <div class="feed-row">
                                <span class="feed-what">Weight used</span>
                                <div class="stepper">
                                    <button type="button" class="step" data-step="-5" aria-label="Five kg less">−</button>
                                    <input type="number" class="step-val step-val--wide" data-loose
                                           name="loose_kg[{{ $feed->id }}]"
                                           value="{{ old("loose_kg.{$feed->id}", ($row && $row->bags === 0) ? (int) $row->quantity_kg : 0) }}"
                                           min="0" max="20000" step="1" inputmode="numeric">
                                    <button type="button" class="step" data-step="5" aria-label="Five kg more">+</button>
                                </div>
                            </div>
                            <p class="bag-size">kilograms</p>
                        </div>

                        <label class="loose-toggle">
                            <input type="checkbox" name="loose[{{ $feed->id }}]" value="1"
                                   @checked($isLoose) data-loose-toggle>
                            <span>Weighed loose, not in bags</span>
                        </label>

                        <p class="feed-sum" data-sum>= 0 kg</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach

    <div class="ask" style="margin-top:24px;">
        <p class="ask-q">Anything to add?</p>
        <p class="ask-hint">You can leave this empty.</p>
        <textarea name="worker_note" rows="2"
                  placeholder="e.g. one bag was spilled">{{ old('worker_note') }}</textarea>
    </div>

    <button type="submit" class="btn-big">Save</button>
</form>

<script>
(function () {
    var form = document.getElementById('feedForm');
    var out  = document.getElementById('totalKg');
    if (!form || !out) return;

    /* One feed's contribution, whichever way it was measured. */
    function sumOne(block) {
        var loose = block.querySelector('[data-loose-toggle]').checked;

        if (loose) {
            return parseInt(block.querySelector('[data-loose]').value, 10) || 0;
        }

        var bags = parseInt(block.querySelector('[data-bags]').value, 10) || 0;
        var kg   = parseInt(block.querySelector('[data-kg-final]').value, 10) || 0;

        return bags * kg;
    }

    /* Whichever the worker touched last wins — the quick buttons and the
       typed box would otherwise disagree silently. */
    function settleWeight(block) {
        var custom = block.querySelector('[data-kg-custom]');
        var final  = block.querySelector('[data-kg-final]');
        var picked = block.querySelector('[data-kg]:checked');
        var label  = block.querySelector('[data-bagsize]');

        var kg = custom && custom.value
            ? parseInt(custom.value, 10)
            : (picked ? parseInt(picked.value, 10) : parseInt(final.value, 10));

        kg = Math.max(1, Math.min(500, kg || 50));

        final.value = kg;
        if (label) label.textContent = kg;
    }

    function refresh() {
        var total = 0;

        form.querySelectorAll('[data-feed]').forEach(function (feed) {
            var on     = feed.querySelector('[data-toggle]').checked;
            var amount = feed.querySelector('[data-amount]');

            amount.hidden = !on;
            feed.classList.toggle('feed--on', on);

            if (!on) return;

            var loose = amount.querySelector('[data-loose-toggle]').checked;
            amount.querySelector('[data-mode="bags"]').hidden  = loose;
            amount.querySelector('[data-mode="loose"]').hidden = !loose;

            if (!loose) settleWeight(amount);

            var kg = sumOne(amount);
            amount.querySelector('[data-sum]').textContent = '= ' + kg.toLocaleString() + ' kg';
            total += kg;
        });

        out.textContent = total.toLocaleString();
    }

    form.addEventListener('click', function (e) {
        var open = e.target.closest('[data-open-size]');
        if (open) {
            var pick = open.closest('[data-mode="bags"]').querySelector('[data-size-pick]');
            pick.hidden = !pick.hidden;
            return;
        }

        var btn = e.target.closest('.step');
        if (btn) {
            var input = btn.parentNode.querySelector('.step-val');
            var max   = parseInt(input.max, 10) || 999;
            var now   = parseInt(input.value, 10) || 0;
            input.value = Math.max(0, Math.min(max, now + parseInt(btn.dataset.step, 10)));
        }

        refresh();
    });

    /* Typing a size clears the quick pick, and vice versa. */
    form.addEventListener('input', function (e) {
        if (e.target.matches('[data-kg-custom]') && e.target.value) {
            var chosen = e.target.closest('[data-size-pick]').querySelector('[data-kg]:checked');
            if (chosen) chosen.checked = false;
        }
        refresh();
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('[data-kg]')) {
            var custom = e.target.closest('[data-size-pick]').querySelector('[data-kg-custom]');
            if (custom) custom.value = '';
        }
        refresh();
    });

    refresh();
})();
</script>

@endsection
