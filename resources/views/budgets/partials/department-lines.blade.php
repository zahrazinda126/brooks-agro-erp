{{--
    Weekly budget lines: one aligned column per value. Editable lines keep their inputs in
    their own cells, tied to the row's form with the form="" attribute.
    Needs: $budgetLines, $locked; optional $sections (to offer "Move to unit" for lines outside them).
--}}
@php $editable = fn ($line) => ! $locked && $line->status === 'draft'; @endphp
<table class="exec-table budget-lines">
    <thead><tr><th>Item</th><th>Unit</th><th class="num">Quantity</th><th class="num">Unit price (UGX)</th><th class="num">Total (UGX)</th><th>Notes</th><th></th></tr></thead>
    <tbody>
    @forelse($budgetLines as $line)
        @php $formId = 'budget-line-'.$line->id; $outside = isset($sections) && count($sections) > 1 && ! in_array($line->category, $sections, true); @endphp
        <tr>
            <td data-label="Item"><strong>{{ $line->item }}</strong>
                @if($outside)<small class="table-sub">{{ $line->category }}</small>@endif
                @if($line->carryover_type === 'unfunded')<small class="table-sub">Unfunded last week</small>@endif
                @if($outside && $editable($line))<select class="budget-move" aria-label="Move to unit" name="category" form="{{ $formId }}" onchange="this.form.requestSubmit()"><option value="">Move to unit…</option>@foreach($sections as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>@endif
            </td>
            <td data-label="Unit">{{ $line->unit ?: '—' }}</td>
            @if($editable($line))
                <td class="num" data-label="Quantity"><input form="{{ $formId }}" aria-label="Quantity" name="quantity" type="number" min="0" step="0.01" value="{{ (float) $line->quantity }}" data-budget-qty></td>
                <td class="num" data-label="Unit price"><input form="{{ $formId }}" aria-label="Unit price" name="unit_price" type="number" min="0" step="1" value="{{ (float) $line->unit_price }}" data-budget-price></td>
                <td class="num" data-label="Total"><output data-budget-total>{{ number_format((float) $line->planned_amount) }}</output></td>
                <td data-label="Notes"><input form="{{ $formId }}" aria-label="Notes" name="notes" value="{{ $line->notes }}" placeholder="—"></td>
                <td class="budget-lines__buttons">
                    <form method="POST" action="{{ route('department-budget.update', $line) }}" id="{{ $formId }}">@csrf @method('PUT')</form>
                    <button class="btn btn--small" form="{{ $formId }}">Save</button>
                    <button class="btn btn--quiet btn--small" form="{{ $formId }}" formaction="{{ route('department-budget.destroy', $line) }}" name="_method" value="DELETE" data-confirm="Remove {{ $line->item }}?">Remove</button>
                </td>
            @else
                <td class="num">{{ number_format((float) $line->quantity, 2) }}</td><td class="num">{{ number_format((float) $line->unit_price) }}</td><td class="num">{{ number_format((float) $line->planned_amount) }}</td><td>{{ $line->notes ?: '—' }}</td><td>{{ ucfirst($line->status) }}</td>
            @endif
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No requirements for this week.</td></tr>
    @endforelse
    </tbody>
    @if($budgetLines->isNotEmpty())
    <tfoot><tr><th colspan="4">{{ $subtotalLabel ?? 'Subtotal' }}</th><th class="num">{{ number_format((float) $budgetLines->sum('planned_amount')) }}</th><th colspan="2"></th></tr></tfoot>
    @endif
</table>
@once
<script>
// Live line total while typing quantity or price.
document.addEventListener('input', (e) => {
    const row = e.target.closest('.budget-lines tr');
    if (!row || !e.target.matches('[data-budget-qty],[data-budget-price]')) return;
    const qty = parseFloat(row.querySelector('[data-budget-qty]').value) || 0;
    const price = parseFloat(row.querySelector('[data-budget-price]').value) || 0;
    row.querySelector('[data-budget-total]').textContent = Math.round(qty * price).toLocaleString('en-US');
});
</script>
@endonce
