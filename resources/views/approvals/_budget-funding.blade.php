<div class="summary-grid summary-grid--compact">
    <x-stat-card label="Requested">@money($fundingSummary['requested'], 2)</x-stat-card>
    <x-stat-card label="Funded">@money($fundingSummary['funded'], 2)</x-stat-card>
    <x-stat-card label="Awaiting funds">{{ $fundingSummary['unfunded'] }}</x-stat-card>
    <x-stat-card label="Cancelled">{{ $fundingSummary['cancelled'] }}</x-stat-card>
</div>

<section class="budget-sections" aria-labelledby="budget-sections-title">
    <div class="section-heading">
        <div><p class="eyebrow">Budget composition</p><h2 id="budget-sections-title">Detailed unit budgets</h2><p>Open each unit to review its requirements, quantities, prices, notes, and total.</p></div>
        <strong>{{ $budgetSections->count() }} units</strong>
    </div>

    @if($canManageFunding)
        <form method="POST" action="{{ route('approvals.budget-funding.update',$approval) }}">
            @csrf
    @endif
    <div class="unit-budget-list">
        @foreach($budgetLines->groupBy('category') as $category=>$sectionLines)
            @php
                $sectionTotal=(float)$sectionLines->sum('planned_amount');
                $sectionExceptions=$sectionLines->whereIn('funding_status',['partial','unfunded','cancelled'])->count();
            @endphp
            <details class="card disclosure unit-budget" @if($errors->any()) open @endif>
                <summary>
                    <span><strong>{{ $category }}</strong><small>{{ $sectionLines->count() }} {{ Str::plural('item',$sectionLines->count()) }}@if($sectionExceptions) · {{ $sectionExceptions }} funding exception(s)@endif</small></span>
                    <strong>@money($sectionTotal, 2)</strong>
                </summary>
                <div class="disclosure__body table-wrap">
                    <table class="exec-table funding-table">
                        <thead><tr><th>Requirement</th><th>Quantity</th><th class="num">Unit price</th><th class="num">Requested</th>@if($canManageFunding)<th>Funding decision</th><th class="num">Partial amount</th><th>Decision reason</th>@else<th>Status</th><th>Decision note</th>@endif</tr></thead>
                        <tbody>
                        @foreach($sectionLines as $line)<tr>
                            <td><strong>{{ $line->item ?: 'Item' }}</strong>@if($line->notes)<small>{{ $line->notes }}</small>@endif</td>
                            <td>{{ number_format((float)$line->quantity,2) }} {{ $line->unit }}</td>
                            <td class="num">@money($line->unit_price, 2)</td>
                            <td class="num"><strong>@money($line->planned_amount, 2)</strong></td>
                            @if($canManageFunding)
                                <td><select name="lines[{{ $line->id }}][decision]">@foreach(['funded'=>'Funded (default)','partial'=>'Partially funded','unfunded'=>'Unfunded — keep for later','cancelled'=>'Cancelled'] as $value=>$label)<option value="{{ $value }}" @selected(old("lines.{$line->id}.decision",$line->funding_status==='pending'?'funded':$line->funding_status)===$value)>{{ $label }}</option>@endforeach</select></td>
                                <td class="num"><input type="number" min="0" step="0.01" name="lines[{{ $line->id }}][funded_amount]" value="{{ old("lines.{$line->id}.funded_amount",$line->funded_amount) }}" aria-label="Partial funded amount for {{ $line->item }}">@error("lines.{$line->id}.funded_amount")<span class="error">{{ $message }}</span>@enderror</td>
                                <td><textarea name="lines[{{ $line->id }}][note]" rows="2" aria-label="Decision reason for {{ $line->item }}">{{ old("lines.{$line->id}.note",$line->funding_note) }}</textarea>@error("lines.{$line->id}.note")<span class="error">{{ $message }}</span>@enderror</td>
                            @else
                                <td><x-status-pill :status="$line->funding_status" /></td><td>{{ $line->funding_note ?: '—' }}</td>
                            @endif
                        </tr>@endforeach
                        </tbody>
                        <tfoot><tr><th colspan="3">{{ $category }} total</th><th class="num">@money($sectionTotal, 2)</th><th colspan="3"></th></tr></tfoot>
                    </table>
                </div>
            </details>
        @endforeach
    </div>
    @if($canManageFunding)
        <div class="sticky-actions"><span>Only change items that are not fully funded.</span><button class="btn btn--inline">Save funding exceptions</button></div>
        </form>
    @endif
</section>
