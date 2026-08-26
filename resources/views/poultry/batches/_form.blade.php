{{--
    Shared by create and edit.

    Ordered the way birds actually arrive: what they are, when they came,
    how many, what they cost. Age on arrival sits next to the date because
    the two together are what every later calculation depends on.
--}}

<div class="card">
    <p class="card-label">What arrived</p>

    <div class="form-grid">
        <div class="field @error('code') field--error @enderror">
            <label for="code">Batch code</label>
            <input id="code" name="code" type="text"
                   value="{{ old('code', $batch->code ?? $nextCode ?? '') }}"
                   @if (! isset($batch)) readonly @endif>
            @error('code')<p class="field-error">{{ $message }}</p>@enderror
            @if (! isset($batch))
                <p class="field-hint">Allocated automatically.</p>
            @endif
        </div>

        <div class="field">
            <label for="name">Name</label>
            <input id="name" name="name" type="text"
                   value="{{ old('name', $batch->name ?? '') }}"
                   placeholder="e.g. House 2 layers">
            <p class="field-hint">Optional. Helps people recognise it faster than a code.</p>
        </div>

        <div class="field @error('department_id') field--error @enderror">
            <label for="department_id">Section <span class="req">required</span></label>
            <select id="department_id" name="department_id" required>
                <option value="">Choose a section</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}"
                        @selected((int) old('department_id', $batch->department_id ?? 0) === $d->id)>
                        {{ $d->name }}
                    </option>
                @endforeach
            </select>
            @error('department_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="purpose">Kept for <span class="req">required</span></label>
            <select id="purpose" name="purpose" required>
                <option value="layer" @selected(old('purpose', $batch->purpose ?? 'layer') === 'layer')>
                    Eggs (layers)
                </option>
                <option value="broiler" @selected(old('purpose', $batch->purpose ?? '') === 'broiler')>
                    Meat (broilers)
                </option>
            </select>
            <p class="field-hint">Almost always layers on this farm.</p>
        </div>

        <div class="field">
            <label for="breed_name">Breed</label>
            <input id="breed_name" name="breed_name" list="poultry-breed-options"
                   value="{{ old('breed_name', $batch->breed?->name ?? '') }}"
                   placeholder="Choose or type a new breed" autocomplete="off">
            <datalist id="poultry-breed-options">
                @foreach ($breeds as $b)
                    <option value="{{ $b->name }}">{{ ucfirst($b->purpose) }}</option>
                @endforeach
            </datalist>
            <p class="field-hint">Choose a known breed or type a new one. A new breed is saved for future batches.</p>
        </div>

        @if (isset($batch))
            <div class="field">
                <label for="house">House or pen</label>
                <input id="house" name="house" type="text" value="{{ old('house', $batch->house ?? '') }}">
                <p class="field-hint">Use Houses & Worker Assignment to change the detailed allocation.</p>
            </div>
        @endif
    </div>
</div>

<div class="card" data-cost-calculator>
    <p class="card-label">When and how many</p>

    <div class="form-grid">
        <div class="field @error('received_date') field--error @enderror">
            <label for="received_date">Date received <span class="req">required</span></label>
            <input id="received_date" name="received_date" type="date"
                   value="{{ old('received_date', isset($batch->received_date) ? $batch->received_date->format('Y-m-d') : now()->format('Y-m-d')) }}"
                   max="{{ now()->format('Y-m-d') }}" required>
            @error('received_date')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('age_days_on_arrival') field--error @enderror">
            <label for="age_days_on_arrival">Age on arrival (days)</label>
            <input id="age_days_on_arrival" name="age_days_on_arrival" type="number"
                   inputmode="numeric" min="0" max="400"
                   value="{{ old('age_days_on_arrival', $batch->age_days_on_arrival ?? 0) }}">
            @error('age_days_on_arrival')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">
                0 for day-old chicks. Point-of-lay pullets are usually about 112 days
                (16 weeks). Everything the system predicts counts from hatch, not arrival.
            </p>
        </div>

        <div class="field @error('quantity_received') field--error @enderror">
            <label for="quantity_received">Birds paid for <span class="req">required</span></label>
            <input id="quantity_received" name="quantity_received" type="number"
                   inputmode="numeric" min="1" data-paid data-cost-quantity
                   value="{{ old('quantity_received', $batch->quantity_received ?? '') }}" required>
            @error('quantity_received')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">The number on the invoice.</p>
        </div>

        {{--
            Suppliers throw in extras to cover losses in transit &mdash; usually
            about 2%, but it varies by supplier and by consignment, so it is
            typed rather than assumed.

            Kept separate from the paid figure because the two answer
            different questions: paid decides the money, total decides the
            birds.
        --}}
        <div class="field @error('quantity_free') field--error @enderror">
            <label for="quantity_free">Extra birds given free</label>
            <input id="quantity_free" name="quantity_free" type="number"
                   inputmode="numeric" min="0" max="100000" data-free
                   value="{{ old('quantity_free', $batch->quantity_free ?? 0) }}">
            @error('quantity_free')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">
                Extras thrown in to cover losses in transit, not on the invoice.
                Usually around 2%, but it varies. <span data-free-pct></span>
            </p>
        </div>

        @if (! isset($batch))
        <div class="field @error('transit_deaths') field--error @enderror">
            <label for="transit_deaths">Birds that died in transit</label>
            <input id="transit_deaths" name="transit_deaths" type="number"
                   inputmode="numeric" min="0" max="1000000" data-transit-deaths
                   value="{{ old('transit_deaths', $batch->transit_deaths ?? 0) }}">
            @error('transit_deaths')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Counted at arrival. These birds are recorded, but are not allocated to a brooder or house.</p>
        </div>
        @endif

        <div class="field @error('unit_cost') field--error @enderror">
            <label for="unit_cost">Cost per bird (UGX)</label>
            <input id="unit_cost" name="unit_cost" type="number"
                   inputmode="numeric" min="0" step="1" data-cost data-cost-unit
                   value="{{ old('unit_cost', isset($batch) ? (int) $batch->unit_cost : '') }}">
            @error('unit_cost')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">What the invoice charges for each paid bird.</p>
        </div>

        <div class="field @error('total_cost') field--error @enderror">
            <label for="total_cost">Total paid (UGX)</label>
            <input id="total_cost" name="total_cost" type="number" inputmode="numeric"
                   min="0" step="0.01" data-cost-total
                   value="{{ old('total_cost', isset($batch) ? $batch->total_cost : '') }}">
            @error('total_cost')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Enter this instead of cost per bird to calculate the unit cost.</p>
        </div>

        {{-- Three figures, because the free birds change two of them. --}}
        <div class="field field--wide">
            <div class="arrival-summary">
                <div>
                    <span class="figure" data-total-birds>&mdash;</span>
                    <span class="figure-unit">Live birds to allocate</span>
                </div>
                <div>
                    <span class="figure" data-total-cost>&mdash;</span>
                    <span class="figure-unit">UGX &middot; total paid</span>
                </div>
                <div>
                    <span class="figure" data-real-cost>&mdash;</span>
                    <span class="figure-unit">UGX &middot; real cost per bird</span>
                </div>
            </div>
            <p class="field-hint" style="margin-top:8px;">
                The free birds lower the real cost per bird. That is the point of
                a supplier giving them, and the figure the farm should judge the
                deal on.
            </p>
        </div>
    </div>
</div>

<div class="card">
    <p class="card-label">Where it came from</p>

    <div class="form-grid">
        <div class="field">
            <label for="supplier_name">Supplier</label>
            <input id="supplier_name" name="supplier_name" type="text"
                   value="{{ old('supplier_name', $batch->supplier_name ?? '') }}">
        </div>

        <div class="field">
            <label for="invoice_no">Invoice number</label>
            <input id="invoice_no" name="invoice_no" type="text"
                   value="{{ old('invoice_no', $batch->invoice_no ?? '') }}">
        </div>

        <div class="field field--wide">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes', $batch->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

@if (! isset($batch))
<div class="card">
    <p class="card-label">Final step · Allocate birds to houses</p>
    <p class="field-hint">The farm has 43 poultry houses. Allocate only the live birds received: paid birds + free birds − transit deaths. Chicks aged 42 days or less must go into brooders.</p>
    @error('house_allocations')<p class="field-error">{{ $message }}</p>@enderror
    <div class="house-allocation-list">
        @forelse ($availableHouses as $house)
            <label class="house-allocation-row">
                <span><strong>{{ $house->house_number }}</strong><small>{{ $house->house_type === 'brooder' ? 'Brooder' : 'Standard house' }} · Available</small></span>
                <input type="number" min="0" name="house_allocations[{{ $house->id }}]"
                       value="{{ old('house_allocations.'.$house->id) }}" placeholder="Number of birds">
            </label>
        @empty
            <div class="notice">All registered houses are currently occupied. You can create a house for this batch below.</div>
        @endforelse
    </div>

    <details class="disclosure" style="margin-top:20px">
        <summary>Add a house outside the current 1–43 register</summary>
        <div class="disclosure__body">
            <p class="field-hint">Use this only when the farm opens an additional poultry house.</p>
            <div class="house-allocation-list">
                @for ($row = 0; $row < 2; $row++)
                    <div class="house-allocation-row">
                        <input name="new_houses[{{ $row }}][house_number]" value="{{ old('new_houses.'.$row.'.house_number') }}" placeholder="House number">
                        <select name="new_houses[{{ $row }}][house_type]"><option value="standard">Standard house</option><option value="brooder" @selected(old('new_houses.'.$row.'.house_type')==='brooder')>Brooder</option></select>
                        <input type="number" min="0" name="new_houses[{{ $row }}][bird_quantity]"
                               value="{{ old('new_houses.'.$row.'.bird_quantity') }}" placeholder="Birds">
                    </div>
                    @error('new_houses.'.$row.'.house_number')<p class="field-error">{{ $message }}</p>@enderror
                @endfor
            </div>
        </div>
    </details>
</div>
@endif

<script>
/*
 | Live arithmetic, so the person entering can see the deal as they type.
 |
 | Free birds add to the count but never to the cost, which is why the
 | real cost per bird falls as they go up. Showing the percentage lets
 | someone sanity-check the supplier against the usual 2%.
 */
(function () {
    var paid = document.querySelector('[data-paid]');
    var free = document.querySelector('[data-free]');
    var cost = document.querySelector('[data-cost]');
    var transitDeaths = document.querySelector('[data-transit-deaths]');

    var outBirds = document.querySelector('[data-total-birds]');
    var outCost  = document.querySelector('[data-total-cost]');
    var outReal  = document.querySelector('[data-real-cost]');
    var outPct   = document.querySelector('[data-free-pct]');

    if (!paid || !outBirds) return;

    function recalc() {
        var p = parseInt(paid.value, 10) || 0;
        var f = parseInt(free.value, 10) || 0;
        var c = parseFloat(cost.value) || 0;

        var d = parseInt(transitDeaths && transitDeaths.value, 10) || 0;
        var birds = Math.max(0, p + f - d);
        var total = p * c;

        outBirds.textContent = birds ? birds.toLocaleString() : '\u2014';
        outCost.textContent  = total ? Math.round(total).toLocaleString() : '\u2014';
        outReal.textContent  = (birds && total)
            ? Math.round(total / birds).toLocaleString()
            : '\u2014';

        if (outPct) {
            outPct.textContent = (p && f)
                ? 'That is ' + (f / p * 100).toFixed(1) + '% extra.'
                : '';
        }
    }

    [paid, free, transitDeaths, cost].forEach(function (el) {
        if (el) el.addEventListener('input', recalc);
    });

    recalc();
})();
</script>

