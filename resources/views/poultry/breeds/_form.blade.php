{{--
    Shared by add and edit.

    The figures asked for here are the ones printed on a hatchery delivery
    note or breed sheet. Someone receiving birds can copy them across
    without needing to know poultry science.
--}}

<div class="card">
    <p class="card-label">The breed</p>

    <div class="form-grid">
        <div class="field @error('name') field--error @enderror">
            <label for="name">Name <span class="req">required</span></label>
            <input id="name" name="name" type="text"
                   value="{{ old('name', $breed->name ?? '') }}"
                   placeholder="e.g. Novogen Brown" required>
            @error('name')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Exactly as the supplier calls it.</p>
        </div>

        <div class="field">
            <label for="purpose">Kept for <span class="req">required</span></label>
            <select id="purpose" name="purpose" required data-purpose>
                @foreach (['layer' => 'Eggs (layers)', 'broiler' => 'Meat (broilers)', 'dual' => 'Both (dual purpose)'] as $v => $label)
                    <option value="{{ $v }}" @selected(old('purpose', $breed->purpose ?? 'layer') === $v)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>

{{-- Laying figures. Hidden for broilers, which never lay. --}}
<div class="card" data-block="laying">
    <p class="card-label">Laying</p>
    <p class="muted u-m0-b14">
        These come off the delivery note or breed sheet. They are what tells the
        system when eggs are due, what size to expect, and when a flock is behind.
    </p>

    <div class="form-grid">
        <div class="field @error('lay_start_week') field--error @enderror">
            <label for="lay_start_week">Laying starts — week</label>
            <input id="lay_start_week" name="lay_start_week" type="number"
                   inputmode="numeric" min="10" max="52"
                   value="{{ old('lay_start_week', $breed->lay_start_week ?? '') }}"
                   placeholder="18">
            @error('lay_start_week')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Point of lay. Commercial layers 18–20, local birds 24–28.</p>
        </div>

        <div class="field @error('peak_week') field--error @enderror">
            <label for="peak_week">Peak — week</label>
            <input id="peak_week" name="peak_week" type="number"
                   inputmode="numeric" min="10" max="80"
                   value="{{ old('peak_week', $breed->peak_week ?? '') }}"
                   placeholder="28">
            @error('peak_week')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">When the flock lays its highest daily rate.</p>
        </div>

        <div class="field @error('peak_lay_percent') field--error @enderror">
            <label for="peak_lay_percent">Peak rate (%)</label>
            <input id="peak_lay_percent" name="peak_lay_percent" type="number"
                   inputmode="numeric" min="10" max="100"
                   value="{{ old('peak_lay_percent', $breed->peak_lay_percent ?? '') }}"
                   placeholder="95">
            @error('peak_lay_percent')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">95 means 95 eggs a day from every 100 hens.</p>
        </div>

        <div class="field @error('lay_end_week') field--error @enderror">
            <label for="lay_end_week">Laying ends — week</label>
            <input id="lay_end_week" name="lay_end_week" type="number"
                   inputmode="numeric" min="20" max="150"
                   value="{{ old('lay_end_week', $breed->lay_end_week ?? '') }}"
                   placeholder="80">
            @error('lay_end_week')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">When the flock is normally culled.</p>
        </div>
    </div>
</div>

{{-- Broiler figures. --}}
<div class="card" data-block="meat" hidden>
    <p class="card-label">Growing</p>

    <div class="form-grid">
        <div class="field @error('slaughter_week') field--error @enderror">
            <label for="slaughter_week">Ready at — week</label>
            <input id="slaughter_week" name="slaughter_week" type="number"
                   inputmode="numeric" min="3" max="30"
                   value="{{ old('slaughter_week', $breed->slaughter_week ?? '') }}"
                   placeholder="6">
            @error('slaughter_week')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="card">
    <p class="card-label">Size and feed</p>

    <div class="form-grid">
        <div class="field @error('mature_weight_g') field--error @enderror">
            <label for="mature_weight_g">Mature weight (g)</label>
            <input id="mature_weight_g" name="mature_weight_g" type="number"
                   inputmode="numeric" min="200" max="10000"
                   value="{{ old('mature_weight_g', $breed->mature_weight_g ?? '') }}"
                   placeholder="1900">
            @error('mature_weight_g')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('daily_feed_g') field--error @enderror">
            <label for="daily_feed_g">Feed per bird per day (g)</label>
            <input id="daily_feed_g" name="daily_feed_g" type="number"
                   inputmode="numeric" min="10" max="500"
                   value="{{ old('daily_feed_g', $breed->daily_feed_g ?? '') }}"
                   placeholder="115">
            @error('daily_feed_g')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Used later to work out how long your feed stock will last.</p>
        </div>

        <div class="field field--wide">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3"
                      placeholder="Anything worth remembering — supplier, how it performed, conditions it suits.">{{ old('notes', $breed->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<script>
/* Show laying figures for layers, growing figures for broilers, both for
   dual purpose. Nobody should be asked for a point of lay on a broiler. */
(function () {
    var purpose = document.querySelector('[data-purpose]');
    if (!purpose) return;

    function sync() {
        var v = purpose.value;
        document.querySelector('[data-block="laying"]').hidden = (v === 'broiler');
        document.querySelector('[data-block="meat"]').hidden   = (v === 'layer');
    }

    purpose.addEventListener('change', sync);
    sync();
})();
</script>
