@extends('layouts.app')
@section('title',$department->name.' Weekly Budget')
@section('content')
@unless($locked)
<div class="screen-actions screen-actions--sticky"><button type="button" class="btn btn--small" onclick="document.getElementById('add-requirement').showModal()">+ Add item</button></div>
@endunless
<div class="page-head page-head--split"><div><h2>{{ $department->name }} weekly budget</h2></div><form method="GET" class="period-picker"><label>Budget week</label><select name="week" onchange="this.form.submit()"><option value="{{ now()->startOfWeek()->toDateString() }}" @selected($start->isSameDay(now()->startOfWeek()))>Current week</option>@foreach($periods as $period)@if(!$period->period_start->isSameDay(now()->startOfWeek()))<option value="{{ $period->period_start->toDateString() }}" @selected($start->isSameDay($period->period_start))>{{ $period->period_start->format('d M Y') }} · {{ ucfirst($period->status) }}</option>@endif @endforeach</select></form></div>
@php
    $submitState = $locked ? ['submitted', 'Locked: Finance has submitted the combined budget']
        : (! $submission ? ['draft', 'Draft · not yet submitted to Finance']
        : ($changedSinceSubmit ? ['pending', 'Changed since submitted · submit again']
        : ['approved', 'Submitted to Finance '.\Carbon\Carbon::parse($submission->submitted_at)->format('d M, H:i')]));
@endphp
@error('budget')<div class="notice notice--alarm">{{ $message }}</div>@enderror
@unless($locked)<dialog id="add-requirement" class="page-dialog" aria-label="Add requirement"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Add requirement</h3><form method="POST" action="{{ route('department-budget.store') }}" class="form-grid">@csrf<input type="hidden" name="_dialog" value="add-requirement"><input type="hidden" name="week" value="{{ $start->toDateString() }}"><div class="field"><label>Section</label><input name="category" list="budget-sections" value="{{ in_array(request('section'), $sections, true) ? request('section') : $sections[0] }}" required><datalist id="budget-sections">@foreach($sections as $section)<option value="{{ $section }}">@endforeach</datalist></div><div class="field"><label>Item / requirement</label><input name="item" required></div><div class="field"><label>Measure unit</label><input name="unit" placeholder="kg, litres, trips"></div><div class="field"><label>Quantity</label><input name="quantity" type="number" min="0.01" step="0.01" required></div><div class="field"><label>Unit price (UGX)</label><input name="unit_price" type="number" min="0" step="0.01" required></div><div class="field field--wide"><label>Reason / notes</label><textarea name="notes"></textarea></div><div class="form-actions"><button class="btn">Add to draft</button></div></form></dialog>@endunless
@php
    $unfundedLines = $lines->where('carryover_type', 'unfunded');
    $workingLines = $lines->where('carryover_type', '!=', 'unfunded');
@endphp
@if($unfundedLines->isNotEmpty())<section class="card table-wrap"><div class="section-heading"><div><p class="eyebrow">Carried forward</p><h3>Previously unfunded requirements</h3><p>Kept separately so they are easy to reconsider if money becomes available.</p></div></div>@include('budgets.partials.department-lines',['budgetLines'=>$unfundedLines])</section>@endif
@php
    // Production (and any department with several sections) budgets per unit, plus the combined total.
    $sectionOf = fn ($line) => in_array($line->category, $sections, true) ? $line->category : 'Other';
    $bySection = $workingLines->groupBy($sectionOf);
    $showSection = in_array(request('section'), $sections, true) ? request('section') : null;
@endphp
@if(count($sections) > 1)
<nav class="view-switch" aria-label="Budget by unit" data-keep-link>
    <a href="{{ request()->fullUrlWithQuery(['section' => null]) }}" @if(!$showSection) aria-current="page" @endif>All (combined) <span class="view-switch__count">@money($workingLines->sum('planned_amount'))</span></a>
    @foreach($sections as $section)<a href="{{ request()->fullUrlWithQuery(['section' => $section]) }}" @if($showSection === $section) aria-current="page" @endif>{{ $section }} <span class="view-switch__count">@money(($bySection[$section] ?? collect())->sum('planned_amount'))</span></a>@endforeach
</nav>
@endif
@if($showSection)
<section class="card table-wrap"><div class="section-heading"><div><h3>{{ $showSection }} budget</h3></div></div>@include('budgets.partials.department-lines',['budgetLines'=>$bySection[$showSection] ?? collect(), 'subtotalLabel'=>$showSection.' total'])</section>
@elseif(count($sections) > 1)
@forelse(collect($sections)->push('Other')->filter(fn ($s) => isset($bySection[$s])) as $section)
<section class="card table-wrap"><div class="section-heading"><div><h3>{{ $section }}</h3></div></div>@include('budgets.partials.department-lines',['budgetLines'=>$bySection[$section], 'subtotalLabel'=>$section.' total'])</section>
@empty
<section class="card table-wrap">@include('budgets.partials.department-lines',['budgetLines'=>collect()])</section>
@endforelse
@if($workingLines->isNotEmpty())
{{-- Overall budget: every unit's total and the grand total. --}}
<section class="card table-wrap budget-overall"><div class="section-heading"><div><h3>Overall total</h3></div></div>
<table class="exec-table budget-lines"><thead><tr><th>Unit</th><th class="num">Lines</th><th class="num">Total (UGX)</th></tr></thead><tbody>
@foreach(collect($sections)->push('Other')->filter(fn ($s) => isset($bySection[$s])) as $section)<tr><td data-label="Unit">{{ $section }}</td><td class="num" data-label="Lines">{{ $bySection[$section]->count() }}</td><td class="num" data-label="Total">{{ number_format((float) $bySection[$section]->sum('planned_amount')) }}</td></tr>@endforeach
</tbody><tfoot><tr><th>Overall total</th><th class="num">{{ $workingLines->count() }}</th><th class="num">{{ number_format((float) $workingLines->sum('planned_amount')) }}</th></tr></tfoot></table>
</section>
@endif
@else
<section class="card table-wrap"><div class="section-heading"><div><h3>This week's requirements</h3></div></div>@include('budgets.partials.department-lines',['budgetLines'=>$workingLines, 'subtotalLabel'=>'Overall total'])</section>
@endif
{{-- Status, total and the submit button sit at the end of the draft. --}}
<form method="POST" action="{{ route('department-budget.submit') }}" class="budget-submit-bar">@csrf<input type="hidden" name="week" value="{{ $start->toDateString() }}"><x-status-pill :status="$submitState[0]" :label="$submitState[1]" /><span>Draft total <strong>@money($lines->sum('planned_amount'))</strong> · {{ $lines->count() }} line(s)</span>@if(!$locked && $lines->isNotEmpty() && (!$submission || $changedSinceSubmit))<button class="btn" data-confirm="Submit the {{ $department->name }} budget ({{ $lines->count() }} line(s)) to Finance?">{{ $submission ? 'Submit again' : 'Submit to Finance' }}</button>@endif</form>
<script>
(() => {
    // Reopen the form after a failed save.
    const open = @json(old('_dialog'));
    const dialog = open && document.getElementById(open);
    if (dialog && dialog.showModal) dialog.showModal();
})();
</script>
@endsection
